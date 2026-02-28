<?php

namespace App\Services;

use App\Models\StripeAccount;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

/**
 * StripeService — ALL Stripe API interactions go through this service.
 * Supports multiple Stripe accounts. Every method accepts an optional
 * $stripeAccountId to specify which account to use.
 *
 * Resolution order: explicit account ID → default account in DB → .env fallback.
 * NO other file should make Stripe API calls (except DebugController connectivity test).
 */
class StripeService
{
    /**
     * @var array<int|string, StripeClient> Cached clients keyed by account ID
     */
    protected array $clients = [];

    /**
     * @var GenericService Shared utility service for logging
     */
    protected GenericService $generic;

    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Get a StripeClient for the specified account.
     * Caches clients to avoid re-creating them within the same request.
     *
     * Resolution: explicit ID → default DB account → .env STRIPE_SECRET_KEY fallback.
     *
     * @param int|null $stripeAccountId Specific Stripe account ID, or null for default
     * @return StripeClient
     * @throws \RuntimeException If no Stripe key can be resolved
     */
    protected function getClient(?int $stripeAccountId = null): StripeClient
    {
        // Try to resolve a StripeAccount from DB
        $account = null;
        if ($stripeAccountId) {
            $account = StripeAccount::find($stripeAccountId);
        }
        if (!$account) {
            $account = StripeAccount::getDefault();
        }

        // If we have a DB account, use its key
        if ($account) {
            $cacheKey = $account->id;
            if (!isset($this->clients[$cacheKey])) {
                $secretKey = $account->secret_key;
                if (empty($secretKey)) {
                    throw new \RuntimeException('Stripe account "' . $account->name . '" has no secret key configured.');
                }
                $this->clients[$cacheKey] = new StripeClient($secretKey);
            }
            return $this->clients[$cacheKey];
        }

        // Fallback: use .env key (backward compatibility)
        $cacheKey = '_env';
        if (!isset($this->clients[$cacheKey])) {
            $secretKey = config('hws.stripe.secret_key');
            if (empty($secretKey)) {
                $this->generic->log('error', 'No Stripe account configured and no STRIPE_SECRET_KEY in .env');
                throw new \RuntimeException('No Stripe account configured. Add one in Settings or set STRIPE_SECRET_KEY in .env.');
            }
            $this->clients[$cacheKey] = new StripeClient($secretKey);
        }
        return $this->clients[$cacheKey];
    }

    /**
     * Retrieve a Stripe customer by their Customer ID.
     *
     * @param string   $customerId      Stripe Customer ID (cus_xxxxx)
     * @param int|null $stripeAccountId Which Stripe account to use (null = default)
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getCustomer(string $customerId, ?int $stripeAccountId = null): array
    {
        try {
            $customer = $this->getClient($stripeAccountId)->customers->retrieve($customerId);

            $this->generic->log('info', 'Stripe customer retrieved', [
                'customer_id' => $customerId,
                'account_id' => $stripeAccountId,
                'name' => $customer->name,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id'    => $customer->id,
                    'name'  => $customer->name ?? '',
                    'email' => $customer->email ?? '',
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->generic->log('error', 'Stripe customer retrieval failed', [
                'customer_id' => $customerId,
                'account_id' => $stripeAccountId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a draft invoice on Stripe.
     *
     * @param string   $customerId      Stripe Customer ID
     * @param string   $description     Line item description
     * @param int      $amountCents     Total amount in cents
     * @param int|null $stripeAccountId Which Stripe account to use (null = default)
     * @return array{success: bool, data?: array, error?: string}
     */
    public function createDraftInvoice(
        string $customerId,
        string $description,
        int $amountCents,
        ?int $stripeAccountId = null
    ): array {
        try {
            $client = $this->getClient($stripeAccountId);

            // Create the invoice shell as draft
            $invoice = $client->invoices->create([
                'customer' => $customerId,
                'auto_advance' => false,
                'currency' => strtolower(config('hws.currency')),
            ]);

            // Add line item
            $client->invoiceItems->create([
                'customer' => $customerId,
                'invoice' => $invoice->id,
                'amount' => $amountCents,
                'currency' => strtolower(config('hws.currency')),
                'description' => $description,
            ]);

            $this->generic->log('info', 'Stripe draft invoice created', [
                'invoice_id' => $invoice->id,
                'customer_id' => $customerId,
                'account_id' => $stripeAccountId,
                'amount_cents' => $amountCents,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id'         => $invoice->id,
                    'status'     => $invoice->status,
                    'hosted_url' => $invoice->hosted_invoice_url,
                    'amount_due' => $invoice->amount_due,
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->generic->log('error', 'Stripe invoice creation failed', [
                'customer_id' => $customerId,
                'account_id' => $stripeAccountId,
                'amount_cents' => $amountCents,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Finalize a draft invoice on Stripe.
     *
     * @param string   $invoiceId       Stripe Invoice ID (in_xxxxx)
     * @param int|null $stripeAccountId Which Stripe account to use
     * @return array{success: bool, data?: array, error?: string}
     */
    public function finalizeInvoice(string $invoiceId, ?int $stripeAccountId = null): array
    {
        try {
            $invoice = $this->getClient($stripeAccountId)->invoices->finalizeInvoice($invoiceId);

            $this->generic->log('info', 'Stripe invoice finalized', [
                'invoice_id' => $invoiceId,
                'account_id' => $stripeAccountId,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id'         => $invoice->id,
                    'status'     => $invoice->status,
                    'hosted_url' => $invoice->hosted_invoice_url,
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->generic->log('error', 'Stripe invoice finalization failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a finalized invoice via Stripe email.
     *
     * @param string   $invoiceId       Stripe Invoice ID (in_xxxxx)
     * @param int|null $stripeAccountId Which Stripe account to use
     * @return array{success: bool, data?: array, error?: string}
     */
    public function sendInvoice(string $invoiceId, ?int $stripeAccountId = null): array
    {
        try {
            $invoice = $this->getClient($stripeAccountId)->invoices->sendInvoice($invoiceId);

            $this->generic->log('info', 'Stripe invoice sent', [
                'invoice_id' => $invoiceId,
                'account_id' => $stripeAccountId,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id'     => $invoice->id,
                    'status' => $invoice->status,
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->generic->log('error', 'Stripe invoice send failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retrieve current invoice details from Stripe (for payment status polling).
     *
     * @param string   $invoiceId       Stripe Invoice ID (in_xxxxx)
     * @param int|null $stripeAccountId Which Stripe account to use
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getInvoice(string $invoiceId, ?int $stripeAccountId = null): array
    {
        try {
            $client = $this->getClient($stripeAccountId);
            $invoice = $client->invoices->retrieve($invoiceId);

            $data = [
                'id'               => $invoice->id,
                'status'           => $invoice->status,
                'hosted_url'       => $invoice->hosted_invoice_url,
                'amount_due'       => $invoice->amount_due,
                'amount_paid'      => $invoice->amount_paid,
                'amount_remaining' => $invoice->amount_remaining,
                'paid'             => $invoice->paid,
                'payment_intent'   => $invoice->payment_intent,
            ];

            // If paid, get payment details
            if ($invoice->paid && $invoice->payment_intent) {
                $paymentIntent = $client->paymentIntents->retrieve($invoice->payment_intent);
                $data['payment_details'] = [
                    'payment_method' => $paymentIntent->payment_method,
                    'amount'         => $paymentIntent->amount,
                    'currency'       => $paymentIntent->currency,
                    'status'         => $paymentIntent->status,
                    'created'        => date('Y-m-d H:i:s', $paymentIntent->created),
                ];
            }

            $this->generic->log('info', 'Stripe invoice retrieved', [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status,
                'paid' => $invoice->paid,
            ]);

            return ['success' => true, 'data' => $data];
        } catch (ApiErrorException $e) {
            $this->generic->log('error', 'Stripe invoice retrieval failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test connectivity to a specific Stripe account.
     *
     * @param int|null $stripeAccountId Account to test (null = default)
     * @return array{success: bool, message: string}
     */
    public function testConnection(?int $stripeAccountId = null): array
    {
        try {
            $this->getClient($stripeAccountId)->customers->all(['limit' => 1]);
            return ['success' => true, 'message' => 'Connected successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Retrieve a Stripe subscription by ID with full details.
     * Searches across all Stripe accounts if no account ID specified.
     *
     * @param string   $subscriptionId  Stripe subscription ID (sub_xxx)
     * @param int|null $stripeAccountId Specific account to check (null = search all)
     * @return array{success: bool, data?: array, stripe_account_id?: int, error?: string}
     */
    public function getSubscription(string $subscriptionId, ?int $stripeAccountId = null): array
    {
        $accountsToSearch = [];

        if ($stripeAccountId) {
            $accountsToSearch[] = $stripeAccountId;
        } else {
            // Search all active accounts
            $accountsToSearch = \App\Models\StripeAccount::active()
                ->pluck('id')->toArray();
            // Also try env fallback (null = default)
            $accountsToSearch[] = null;
        }

        foreach ($accountsToSearch as $acctId) {
            try {
                $client = $this->getClient($acctId);
                $sub = $client->subscriptions->retrieve($subscriptionId, [
                    'expand' => ['customer', 'items.data.price.product', 'latest_invoice'],
                ]);

                $data = $this->parseSubscriptionData($sub);
                $data['stripe_account_id'] = $acctId;

                return ['success' => true, 'data' => $data, 'stripe_account_id' => $acctId];
            } catch (\Exception $e) {
                // Not found on this account, continue searching
                continue;
            }
        }

        return ['success' => false, 'error' => 'Subscription not found on any connected Stripe account.'];
    }

    /**
     * List all active subscriptions from a Stripe account.
     *
     * @param int|null $stripeAccountId Account to query (null = default)
     * @param int      $limit           Max results (default 100)
     * @return array{success: bool, subscriptions?: array, error?: string}
     */
    public function listActiveSubscriptions(?int $stripeAccountId = null, int $limit = 100): array
    {
        try {
            $client = $this->getClient($stripeAccountId);
            $allSubs = [];
            $params = ['status' => 'active', 'limit' => min($limit, 100), 'expand' => ['data.customer', 'data.items.data.price.product']];
            $hasMore = true;

            while ($hasMore && count($allSubs) < $limit) {
                $result = $client->subscriptions->all($params);
                foreach ($result->data as $sub) {
                    $allSubs[] = $this->parseSubscriptionData($sub);
                }
                $hasMore = $result->has_more;
                if ($hasMore && count($result->data) > 0) {
                    $params['starting_after'] = end($result->data)->id;
                }
            }

            return ['success' => true, 'subscriptions' => $allSubs];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse a Stripe subscription object into a flat array.
     *
     * @param object $sub Stripe subscription object
     * @return array Parsed subscription data
     */
    protected function parseSubscriptionData(object $sub): array
    {
        $customer = $sub->customer;
        $customerName = is_object($customer) ? ($customer->name ?? '') : '';
        $customerEmail = is_object($customer) ? ($customer->email ?? '') : '';
        $customerId = is_object($customer) ? $customer->id : ($customer ?? '');

        // Extract line items description and product info
        $descriptions = [];
        $productNames = [];
        $totalAmount = 0;
        $interval = 'month';
        $priceId = null;

        if ($sub->items && $sub->items->data) {
            foreach ($sub->items->data as $item) {
                if ($item->price) {
                    $totalAmount += $item->price->unit_amount * ($item->quantity ?? 1);
                    $interval = $item->price->recurring->interval ?? 'month';
                    $priceId = $priceId ?? $item->price->id;

                    if ($item->price->product && is_object($item->price->product)) {
                        $productNames[] = $item->price->product->name ?? '';
                    }
                }
                // Description from the line item or price nickname
                $desc = $item->description
                    ?? ($item->price->nickname ?? '')
                    ?: ($item->price->product->name ?? '');
                if ($desc) $descriptions[] = $desc;
            }
        }

        // Last payment date from latest invoice
        $lastPayment = null;
        if ($sub->latest_invoice && is_object($sub->latest_invoice)) {
            if ($sub->latest_invoice->status === 'paid' && $sub->latest_invoice->status_transitions) {
                $paidAt = $sub->latest_invoice->status_transitions->paid_at ?? null;
                $lastPayment = $paidAt ? date('Y-m-d H:i:s', $paidAt) : null;
            }
        }

        return [
            'id'                    => $sub->id,
            'status'                => $sub->status,
            'customer_id'           => $customerId,
            'customer_name'         => $customerName,
            'customer_email'        => $customerEmail,
            'product_name'          => implode(', ', array_filter($productNames)),
            'description'           => implode(' | ', array_filter($descriptions)),
            'amount_cents'          => $totalAmount,
            'interval'              => $interval,
            'price_id'              => $priceId,
            'current_period_start'  => $sub->current_period_start ? date('Y-m-d H:i:s', $sub->current_period_start) : null,
            'current_period_end'    => $sub->current_period_end ? date('Y-m-d H:i:s', $sub->current_period_end) : null,
            'next_payment_at'       => $sub->current_period_end ? date('Y-m-d H:i:s', $sub->current_period_end) : null,
            'last_payment_at'       => $lastPayment,
            'canceled_at'           => $sub->canceled_at ? date('Y-m-d H:i:s', $sub->canceled_at) : null,
            'created'               => $sub->created ? date('Y-m-d H:i:s', $sub->created) : null,
        ];
    }

    /**
     * Get detailed customer info from Stripe for display.
     * Includes balance, default payment method, subscriptions count, etc.
     *
     * @param string   $customerId      Stripe customer ID
     * @param int|null $stripeAccountId Account to query
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getCustomerDetails(string $customerId, ?int $stripeAccountId = null): array
    {
        try {
            $client = $this->getClient($stripeAccountId);
            $customer = $client->customers->retrieve($customerId, [
                'expand' => ['default_source', 'subscriptions'],
            ]);

            $activeSubs = 0;
            $totalMrr = 0;
            if ($customer->subscriptions && $customer->subscriptions->data) {
                foreach ($customer->subscriptions->data as $sub) {
                    if ($sub->status === 'active') {
                        $activeSubs++;
                        foreach ($sub->items->data as $item) {
                            $totalMrr += ($item->price->unit_amount ?? 0) * ($item->quantity ?? 1);
                        }
                    }
                }
            }

            return [
                'success' => true,
                'data' => [
                    'id'                  => $customer->id,
                    'name'                => $customer->name ?? '',
                    'email'               => $customer->email ?? '',
                    'phone'               => $customer->phone ?? '',
                    'currency'            => $customer->currency ?? 'usd',
                    'balance_cents'       => $customer->balance ?? 0,
                    'created'             => $customer->created ? date('M j, Y', $customer->created) : null,
                    'delinquent'          => $customer->delinquent ?? false,
                    'active_subscriptions' => $activeSubs,
                    'mrr_cents'           => $totalMrr,
                    'default_source'      => $customer->default_source ? (is_object($customer->default_source) ? ($customer->default_source->last4 ?? 'on file') : 'on file') : null,
                    'metadata'            => (array) ($customer->metadata ?? []),
                    'dashboard_url'       => 'https://dashboard.stripe.com/customers/' . $customer->id,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a Stripe invoice with one or more line items.
     * Returns the created invoice with hosted URL and ID.
     *
     * @param string   $customerId      Stripe customer ID
     * @param array    $lineItems       Array of ['description' => ..., 'amount_cents' => ...]
     * @param int|null $stripeAccountId Account to use
     * @param int      $dueDays         Days until due (0 = due on receipt)
     * @param string|null $memo         Invoice memo/note
     * @return array{success: bool, data?: array, error?: string}
     */
    public function createInvoiceWithItems(
        string $customerId,
        array $lineItems,
        ?int $stripeAccountId = null,
        int $dueDays = 30,
        ?string $memo = null,
    ): array {
        try {
            $client = $this->getClient($stripeAccountId);
            $currency = strtolower(config('hws.currency', 'usd'));

            // Create the invoice shell as draft
            $invoiceParams = [
                'customer' => $customerId,
                'auto_advance' => false,
                'currency' => $currency,
                'collection_method' => 'send_invoice',
                'days_until_due' => $dueDays,
            ];
            if ($memo) $invoiceParams['description'] = $memo;

            $invoice = $client->invoices->create($invoiceParams);

            // Add each line item
            foreach ($lineItems as $item) {
                $client->invoiceItems->create([
                    'customer'    => $customerId,
                    'invoice'     => $invoice->id,
                    'amount'      => $item['amount_cents'],
                    'currency'    => $currency,
                    'description' => $item['description'],
                ]);
            }

            // Retrieve updated invoice to get totals
            $invoice = $client->invoices->retrieve($invoice->id);

            $this->generic->log('info', 'Invoice created via Invoicing Center', [
                'invoice_id'  => $invoice->id,
                'customer_id' => $customerId,
                'account_id'  => $stripeAccountId,
                'items'       => count($lineItems),
                'total'       => $invoice->amount_due,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id'          => $invoice->id,
                    'status'      => $invoice->status,
                    'hosted_url'  => $invoice->hosted_invoice_url,
                    'pdf_url'     => $invoice->invoice_pdf,
                    'amount_due'  => $invoice->amount_due,
                    'currency'    => $invoice->currency,
                    'customer'    => $customerId,
                    'created'     => date('Y-m-d H:i:s', $invoice->created),
                    'dashboard_url' => 'https://dashboard.stripe.com/invoices/' . $invoice->id,
                ],
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->generic->log('error', 'Invoice creation failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

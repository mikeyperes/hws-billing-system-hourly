<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

/**
 * StripeService — ALL Stripe API interactions go through this service.
 * Single wrapper for customer retrieval, invoice creation, finalization,
 * sending, and payment status polling.
 *
 * NO other file should make Stripe API calls. This is enforced by spec.
 */
class StripeService
{
    /**
     * @var StripeClient|null The Stripe API client instance
     */
    protected ?StripeClient $stripe = null;

    /**
     * @var GenericService Shared utility service for logging
     */
    protected GenericService $generic;

    /**
     * Constructor — inject the shared GenericService.
     *
     * @param GenericService $generic Shared utility service
     */
    public function __construct(GenericService $generic)
    {
        // Store reference to generic service for logging
        $this->generic = $generic;
    }

    /**
     * Get or create the Stripe API client instance.
     * Lazy-loaded — only initialized when first needed.
     *
     * @return StripeClient The configured Stripe client
     * @throws \RuntimeException If the Stripe secret key is not configured
     */
    protected function getClient(): StripeClient
    {
        // Return existing client if already initialized
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        // Get the secret key from config/hws.php (which reads from .env)
        $secretKey = config('hws.stripe.secret_key');

        // Validate that the key is configured
        if (empty($secretKey)) {
            // Log the error before throwing
            $this->generic->log('error', 'Stripe secret key is not configured');
            // Throw exception — can't proceed without API key
            throw new \RuntimeException('Stripe secret key is not configured. Check .env file.');
        }

        // Create and cache the Stripe client instance
        $this->stripe = new StripeClient($secretKey);

        // Return the initialized client
        return $this->stripe;
    }

    /**
     * Retrieve a Stripe customer by their Customer ID.
     * Used during client import to pull name and email.
     *
     * @param string $customerId Stripe Customer ID (cus_xxxxx)
     * @return array{success: bool, data?: array, error?: string} Result with customer data or error
     */
    public function getCustomer(string $customerId): array
    {
        try {
            // Call the Stripe Customers API to retrieve the customer
            $customer = $this->getClient()->customers->retrieve($customerId);

            // Log the successful retrieval
            $this->generic->log('info', 'Stripe customer retrieved', [
                'customer_id' => $customerId,
                'name' => $customer->name,
            ]);

            // Return the customer data in a standardized format
            return [
                'success' => true,
                'data' => [
                    'id'    => $customer->id,         // Stripe Customer ID
                    'name'  => $customer->name ?? '',  // Customer display name
                    'email' => $customer->email ?? '', // Customer email
                ],
            ];

        } catch (ApiErrorException $e) {
            // Log the API error with details
            $this->generic->log('error', 'Stripe customer retrieval failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response with error message
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a draft invoice on Stripe for a given customer.
     * The invoice is NOT finalized or sent — it stays as a draft.
     *
     * @param string $customerId  Stripe Customer ID
     * @param string $description Line item description (e.g., "Consulting — 25.50 hours")
     * @param int    $amountCents Total amount in cents (Stripe uses smallest currency unit)
     * @return array{success: bool, data?: array, error?: string} Result with invoice data or error
     */
    public function createDraftInvoice(
        string $customerId,
        string $description,
        int $amountCents
    ): array {
        try {
            // Step 1: Create the invoice shell as a draft
            $invoice = $this->getClient()->invoices->create([
                // Link to the Stripe customer
                'customer' => $customerId,
                // Keep as draft — admin will finalize/send when ready
                'auto_advance' => false,
                // Set currency from config
                'currency' => strtolower(config('hws.currency')),
            ]);

            // Step 2: Add a line item to the invoice
            $this->getClient()->invoiceItems->create([
                // Link to the same customer
                'customer' => $customerId,
                // Link to the invoice we just created
                'invoice' => $invoice->id,
                // Total amount in cents
                'amount' => $amountCents,
                // Currency from config
                'currency' => strtolower(config('hws.currency')),
                // Human-readable description of the work
                'description' => $description,
            ]);

            // Log the successful invoice creation
            $this->generic->log('info', 'Stripe draft invoice created', [
                'invoice_id' => $invoice->id,
                'customer_id' => $customerId,
                'amount_cents' => $amountCents,
            ]);

            // Return the invoice data in a standardized format
            return [
                'success' => true,
                'data' => [
                    'id'          => $invoice->id,                // Stripe Invoice ID
                    'status'      => $invoice->status,            // Should be 'draft'
                    'hosted_url'  => $invoice->hosted_invoice_url, // URL for client to view/pay
                    'amount_due'  => $invoice->amount_due,        // Amount in cents
                ],
            ];

        } catch (ApiErrorException $e) {
            // Log the API error with details
            $this->generic->log('error', 'Stripe invoice creation failed', [
                'customer_id' => $customerId,
                'amount_cents' => $amountCents,
                'error' => $e->getMessage(),
            ]);

            // Return failure response with error message
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Finalize a draft invoice on Stripe, making it ready to send.
     * After finalization, the invoice can be sent via Stripe or manually.
     *
     * @param string $invoiceId Stripe Invoice ID (in_xxxxx)
     * @return array{success: bool, data?: array, error?: string} Result with invoice data or error
     */
    public function finalizeInvoice(string $invoiceId): array
    {
        try {
            // Call the Stripe API to finalize the draft invoice
            $invoice = $this->getClient()->invoices->finalizeInvoice($invoiceId);

            // Log the successful finalization
            $this->generic->log('info', 'Stripe invoice finalized', [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status,
            ]);

            // Return the updated invoice data
            return [
                'success' => true,
                'data' => [
                    'id'         => $invoice->id,                  // Stripe Invoice ID
                    'status'     => $invoice->status,              // Should be 'open' after finalization
                    'hosted_url' => $invoice->hosted_invoice_url,  // URL for client to view/pay
                ],
            ];

        } catch (ApiErrorException $e) {
            // Log the API error with details
            $this->generic->log('error', 'Stripe invoice finalization failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a finalized invoice via Stripe's email system.
     * Invoice must be finalized before it can be sent.
     *
     * @param string $invoiceId Stripe Invoice ID (in_xxxxx)
     * @return array{success: bool, data?: array, error?: string} Result with send status or error
     */
    public function sendInvoice(string $invoiceId): array
    {
        try {
            // Call the Stripe API to send the invoice email
            $invoice = $this->getClient()->invoices->sendInvoice($invoiceId);

            // Log the successful send
            $this->generic->log('info', 'Stripe invoice sent', [
                'invoice_id' => $invoiceId,
            ]);

            // Return the updated invoice data
            return [
                'success' => true,
                'data' => [
                    'id'     => $invoice->id,      // Stripe Invoice ID
                    'status' => $invoice->status,   // Should be 'open' or 'sent'
                ],
            ];

        } catch (ApiErrorException $e) {
            // Log the API error with details
            $this->generic->log('error', 'Stripe invoice send failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve current invoice details from Stripe.
     * Used to poll for payment status (draft → open → paid).
     *
     * @param string $invoiceId Stripe Invoice ID (in_xxxxx)
     * @return array{success: bool, data?: array, error?: string} Result with invoice data or error
     */
    public function getInvoice(string $invoiceId): array
    {
        try {
            // Call the Stripe API to retrieve the invoice
            $invoice = $this->getClient()->invoices->retrieve($invoiceId);

            // Build the response data array
            $data = [
                'id'              => $invoice->id,                  // Stripe Invoice ID
                'status'          => $invoice->status,              // Current status
                'hosted_url'      => $invoice->hosted_invoice_url,  // Client-facing URL
                'amount_due'      => $invoice->amount_due,          // Amount due in cents
                'amount_paid'     => $invoice->amount_paid,         // Amount paid in cents
                'amount_remaining' => $invoice->amount_remaining,   // Remaining balance in cents
                'paid'            => $invoice->paid,                // Boolean: fully paid?
                'payment_intent'  => $invoice->payment_intent,      // Payment intent ID if paid
            ];

            // If the invoice is paid, include additional payment details
            if ($invoice->paid && $invoice->payment_intent) {
                // Retrieve the payment intent for detailed payment info
                $paymentIntent = $this->getClient()->paymentIntents->retrieve($invoice->payment_intent);

                // Add payment details to the response
                $data['payment_details'] = [
                    'payment_method' => $paymentIntent->payment_method,  // Payment method ID
                    'amount'         => $paymentIntent->amount,          // Amount in cents
                    'currency'       => $paymentIntent->currency,        // Currency code
                    'status'         => $paymentIntent->status,          // Payment status
                    'created'        => date('Y-m-d H:i:s', $paymentIntent->created), // Payment timestamp
                ];
            }

            // Log the retrieval
            $this->generic->log('info', 'Stripe invoice retrieved', [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status,
                'paid' => $invoice->paid,
            ]);

            // Return the invoice data
            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (ApiErrorException $e) {
            // Log the API error with details
            $this->generic->log('error', 'Stripe invoice retrieval failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientStripeLink;
use App\Models\ListItem;
use App\Models\StripeAccount;
use App\Services\StripeService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * ClientController — handles client CRUD, Stripe import, and credit management.
 * Clients are imported from Stripe using comma-separated Customer IDs.
 * Local fields (hourly rate, billing type, credits) are managed here.
 */
class ClientController extends Controller
{
    /**
     * @var StripeService Stripe API service for customer retrieval
     */
    protected StripeService $stripe;

    /**
     * @var GenericService Shared utility service
     */
    protected GenericService $generic;

    /**
     * Constructor — inject required services.
     *
     * @param StripeService  $stripe  Stripe API service
     * @param GenericService $generic Shared utility service
     */
    public function __construct(StripeService $stripe, GenericService $generic)
    {
        // Store service references
        $this->stripe = $stripe;
        $this->generic = $generic;
    }

    /**
     * Display the client list page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $clients = Client::withCount('invoices')
            ->with('stripeLinks')
            ->orderBy('name')
            ->paginate(config('hws.per_page'));

        return view('clients.index', [
            'clients' => $clients,
        ]);
    }

    /**
     * Show the create client form.
     */
    public function create()
    {
        $billingTypes = ListItem::getValues('customer_billing_type');
        return view('clients.create', ['billingTypes' => $billingTypes]);
    }

    /**
     * Store a new client.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'billing_type' => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:5000',
        ]);

        $client = Client::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'] ?? null,
            'hourly_rate'  => $validated['hourly_rate'] ?? config('hws.default_hourly_rate'),
            'billing_type' => $validated['billing_type'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'is_active'    => true,
        ]);

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Client "' . $client->name . '" created. Attach Stripe profiles below.');
    }

    /**
     * Display the Stripe import tool page.
     */
    public function showImport()
    {
        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();
        return view('clients.import', ['stripeAccounts' => $stripeAccounts]);
    }

    /**
     * Process the Stripe customer import.
     * Accepts comma-separated Stripe Customer IDs, retrieves each from Stripe,
     * and creates local client records.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function processImport(Request $request)
    {
        $request->validate([
            'stripe_ids'        => 'required|string',
            'stripe_account_id' => 'nullable|integer|exists:stripe_accounts,id',
        ]);

        $stripeIds = $this->generic->parseCommaSeparated($request->input('stripe_ids'));
        $stripeAccountId = $request->input('stripe_account_id') ?: null;
        $results = [];

        foreach ($stripeIds as $stripeId) {
            // Check if this customer is already imported (legacy check)
            $existing = Client::where('stripe_customer_id', $stripeId)->first();

            // Also check the pivot table
            if (!$existing && $stripeAccountId) {
                $existingLink = ClientStripeLink::where('stripe_customer_id', $stripeId)
                    ->where('stripe_account_id', $stripeAccountId)
                    ->first();
                if ($existingLink) {
                    $existing = $existingLink->client;
                }
            }

            if ($existing) {
                $results[] = [
                    'stripe_id' => $stripeId,
                    'status'    => 'skipped',
                    'message'   => 'Already imported as "' . $existing->name . '"',
                ];
                continue;
            }

            // Retrieve customer from Stripe (using selected account)
            $stripeResult = $this->stripe->getCustomer($stripeId, $stripeAccountId);

            if (!$stripeResult['success']) {
                $results[] = [
                    'stripe_id' => $stripeId,
                    'status'    => 'error',
                    'message'   => $stripeResult['error'] ?? 'Unknown Stripe error',
                ];
                continue;
            }

            // Create the client record
            $client = Client::create([
                'name'               => $stripeResult['data']['name'],
                'email'              => $stripeResult['data']['email'],
                'stripe_customer_id' => $stripeId, // Legacy field
                'hourly_rate'        => config('hws.default_hourly_rate'),
                'is_active'          => true,
            ]);

            // Create the pivot link if a Stripe account was selected
            if ($stripeAccountId) {
                ClientStripeLink::create([
                    'client_id'          => $client->id,
                    'stripe_account_id'  => $stripeAccountId,
                    'stripe_customer_id' => $stripeId,
                    'is_hourly_billing'  => true, // First import = default hourly
                ]);
            }

            $results[] = [
                'stripe_id' => $stripeId,
                'status'    => 'success',
                'message'   => 'Imported "' . $client->name . '" (' . $client->email . ')',
            ];
        }

        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();

        return view('clients.import', [
            'results'        => $results,
            'originalInput'  => $request->input('stripe_ids'),
            'stripeAccounts' => $stripeAccounts,
        ]);
    }

    /**
     * Display the client edit/detail page.
     *
     * @param Client $client Route model binding
     * @return \Illuminate\View\View
     */
    public function edit(Client $client)
    {
        $billingTypes = ListItem::getValues('customer_billing_type');
        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();
        $stripeLinks = $client->stripeLinks()->with('stripeAccount')->get();

        // Fetch Stripe customer details for each link
        $stripeDetails = [];
        foreach ($stripeLinks as $link) {
            $result = $this->stripe->getCustomerDetails(
                $link->stripe_customer_id,
                $link->stripe_account_id
            );
            $stripeDetails[$link->id] = $result['success'] ? $result['data'] : null;
        }

        return view('clients.edit', [
            'client'         => $client,
            'billingTypes'   => $billingTypes,
            'stripeAccounts' => $stripeAccounts,
            'stripeLinks'    => $stripeLinks,
            'stripeDetails'  => $stripeDetails,
        ]);
    }

    /**
     * Update a client's local billing configuration.
     *
     * @param Request $request
     * @param Client  $client  Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Client $client)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'hourly_rate'   => 'required|numeric|min:0',     // Must be a positive number
            'billing_type'  => 'nullable|string|max:50',     // Optional, from list
            'is_active'     => 'boolean',                     // Checkbox toggle
            'notes'         => 'nullable|string|max:5000',   // Free-form notes
        ]);

        // Update the client record with validated data
        $client->update([
            'hourly_rate'  => $validated['hourly_rate'],                  // New hourly rate
            'billing_type' => $validated['billing_type'] ?? null,         // Billing type (or null)
            'is_active'    => $request->has('is_active'),                 // Checkbox: present = true
            'notes'        => $validated['notes'] ?? null,                // Notes
        ]);

        // Redirect back to the edit page with success message
        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Client updated successfully.');
    }

    /**
     * Adjust a client's credit balance (add or deduct hours).
     *
     * @param Request $request
     * @param Client  $client  Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function adjustCredits(Request $request, Client $client)
    {
        // Validate the credit adjustment input
        $validated = $request->validate([
            'adjustment' => 'required|numeric',        // Can be positive (add) or negative (deduct)
            'note'       => 'nullable|string|max:500',  // Optional reason for the adjustment
        ]);

        // Get the adjustment amount
        $adjustment = (float) $validated['adjustment'];

        // Apply the adjustment to the client's credit balance
        $client->credit_balance_hours += $adjustment;

        // Ensure balance doesn't go below zero
        if ($client->credit_balance_hours < 0) {
            $client->credit_balance_hours = 0;
        }

        // Reset the credit alert flag if balance is now above threshold
        if (!$client->isCreditLow()) {
            $client->credit_alert_sent = false;
        }

        // Save the updated balance
        $client->save();

        // Build the success message
        $action = $adjustment >= 0 ? 'Added' : 'Deducted';
        $message = $action . ' ' . abs($adjustment) . ' hours. New balance: ' . $client->credit_balance_hours . ' hours.';

        // Log the credit adjustment
        $this->generic->log('info', 'Client credit adjusted', [
            'client_id'   => $client->id,
            'adjustment'  => $adjustment,
            'new_balance' => $client->credit_balance_hours,
            'note'        => $validated['note'] ?? '',
        ]);

        // Redirect back with success message
        return redirect()
            ->route('clients.edit', $client)
            ->with('success', $message);
    }

    /**
     * Add a Stripe link to a client.
     */
    public function addStripeLink(Request $request, Client $client)
    {
        $validated = $request->validate([
            'stripe_account_id'  => 'required|exists:stripe_accounts,id',
            'stripe_customer_id' => 'required|string|max:255',
            'is_hourly_billing'  => 'boolean',
            'is_primary_billing' => 'boolean',
        ]);

        // If setting as hourly billing, clear any existing hourly flag
        if ($request->has('is_hourly_billing') && $request->is_hourly_billing) {
            $client->stripeLinks()->update(['is_hourly_billing' => false]);
        }

        // If setting as primary billing, clear any existing primary flag
        if ($request->has('is_primary_billing') && $request->is_primary_billing) {
            $client->stripeLinks()->update(['is_primary_billing' => false]);
        }

        // Check for existing link to this account
        $existing = ClientStripeLink::where('client_id', $client->id)
            ->where('stripe_account_id', $validated['stripe_account_id'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('clients.edit', $client)
                ->with('error', 'Client already has a link to this Stripe account.');
        }

        ClientStripeLink::create([
            'client_id'          => $client->id,
            'stripe_account_id'  => $validated['stripe_account_id'],
            'stripe_customer_id' => $validated['stripe_customer_id'],
            'is_hourly_billing'  => $request->has('is_hourly_billing'),
            'is_primary_billing' => $request->has('is_primary_billing'),
        ]);

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Stripe link added.');
    }

    /**
     * Remove a Stripe link from a client.
     */
    public function removeStripeLink(Client $client, ClientStripeLink $link)
    {
        if ($link->client_id !== $client->id) {
            abort(403);
        }

        $link->delete();

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Stripe link removed.');
    }

    /**
     * Set a Stripe link as the hourly billing profile.
     */
    public function setHourlyBilling(Client $client, ClientStripeLink $link)
    {
        if ($link->client_id !== $client->id) {
            abort(403);
        }

        $client->stripeLinks()->update(['is_hourly_billing' => false]);
        $link->update(['is_hourly_billing' => true]);

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Hourly billing profile updated.');
    }

    /**
     * Set a Stripe link as the primary billing source.
     */
    public function setPrimaryBilling(Client $client, ClientStripeLink $link)
    {
        if ($link->client_id !== $client->id) {
            abort(403);
        }

        $client->stripeLinks()->update(['is_primary_billing' => false]);
        $link->update(['is_primary_billing' => true]);

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Primary billing source updated.');
    }
}

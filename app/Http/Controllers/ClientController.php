<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ListItem;
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
        // Get all clients (including inactive) with their invoice counts
        $clients = Client::withCount('invoices')
            ->orderBy('name')
            ->paginate(config('hws.per_page'));

        // Render the client list view
        return view('clients.index', [
            'clients' => $clients,  // Paginated client collection
        ]);
    }

    /**
     * Display the Stripe import tool page.
     *
     * @return \Illuminate\View\View
     */
    public function showImport()
    {
        // Render the import form (textarea + debug panel)
        return view('clients.import');
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
        // Validate that the stripe_ids field is present and is a string
        $request->validate([
            'stripe_ids' => 'required|string',
        ]);

        // Parse the comma-separated IDs into an array using GenericService
        $stripeIds = $this->generic->parseCommaSeparated($request->input('stripe_ids'));

        // Initialize the results array for the debug panel
        $results = [];

        // Process each Stripe Customer ID
        foreach ($stripeIds as $stripeId) {
            // Check if this customer is already imported
            $existing = Client::where('stripe_customer_id', $stripeId)->first();

            // Skip if already imported
            if ($existing) {
                // Record skip in debug log
                $results[] = [
                    'stripe_id' => $stripeId,
                    'status'    => 'skipped',
                    'message'   => 'Already imported as "' . $existing->name . '"',
                ];
                // Continue to next ID
                continue;
            }

            // Retrieve customer details from Stripe
            $stripeResult = $this->stripe->getCustomer($stripeId);

            // Check if the Stripe call succeeded
            if (!$stripeResult['success']) {
                // Record failure in debug log
                $results[] = [
                    'stripe_id' => $stripeId,
                    'status'    => 'error',
                    'message'   => $stripeResult['error'] ?? 'Unknown Stripe error',
                ];
                // Continue to next ID
                continue;
            }

            // Create the local client record
            $client = Client::create([
                'name'               => $stripeResult['data']['name'],   // Name from Stripe
                'email'              => $stripeResult['data']['email'],  // Email from Stripe
                'stripe_customer_id' => $stripeId,                       // Stripe Customer ID
                'hourly_rate'        => config('hws.default_hourly_rate'), // Default rate from config
                'is_active'          => true,                             // Active by default
            ]);

            // Record success in debug log
            $results[] = [
                'stripe_id' => $stripeId,
                'status'    => 'success',
                'message'   => 'Imported "' . $client->name . '" (' . $client->email . ')',
            ];
        }

        // Render the import page with debug results
        return view('clients.import', [
            'results' => $results,                          // Import debug log
            'originalInput' => $request->input('stripe_ids'), // Preserve textarea content
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
        // Get billing type options from the Lists module
        $billingTypes = ListItem::getValues('customer_billing_type');

        // Render the edit view
        return view('clients.edit', [
            'client'       => $client,       // The client being edited
            'billingTypes' => $billingTypes,  // Dropdown options for billing type
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
}

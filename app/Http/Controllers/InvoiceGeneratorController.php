<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

/**
 * InvoiceGeneratorController — quick invoice generation tool.
 * Allows entering parameters to quickly output Stripe customer IDs
 * and subscription IDs for invoice creation.
 *
 * This is Phase 1 — summary text output.
 * Future phases will auto-create Stripe invoices directly.
 */
class InvoiceGeneratorController extends Controller
{
    /**
     * Show the invoice generator page.
     * Lists clients with their Stripe IDs for quick reference.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all clients with Stripe customer IDs
        $clients = Client::whereNotNull('stripe_customer_id')
            ->where('stripe_customer_id', '!=', '')
            ->orderBy('name')
            ->get();

        return view('invoice-generator.index', [
            'clients' => $clients,
        ]);
    }

    /**
     * Generate invoice summary text.
     * Takes parameters and outputs formatted Stripe IDs and amounts.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function generate(Request $request)
    {
        // Validate inputs
        $validated = $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'description' => 'required|string|max:500',
            'amount'      => 'required|numeric|min:0.01',
            'due_days'    => 'integer|min:0|max:90',
        ]);

        // Get the client
        $client = Client::findOrFail($validated['client_id']);

        // Build summary output
        $summary = [
            'client_name'        => $client->name,
            'client_email'       => $client->email,
            'stripe_customer_id' => $client->stripe_customer_id,
            'description'        => $validated['description'],
            'amount'             => number_format((float)$validated['amount'], 2),
            'amount_cents'       => (int)((float)$validated['amount'] * 100),
            'due_days'           => $validated['due_days'] ?? 30,
            'due_date'           => now()->addDays($validated['due_days'] ?? 30)->format('Y-m-d'),
            'currency'           => config('hws.currency', 'USD'),
        ];

        // Get all clients for the form dropdown
        $clients = Client::whereNotNull('stripe_customer_id')
            ->where('stripe_customer_id', '!=', '')
            ->orderBy('name')
            ->get();

        return view('invoice-generator.index', [
            'clients' => $clients,
            'summary' => $summary,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\Request;

/**
 * ScanController — handles billing scan execution and invoice creation from scan results.
 * The scan process reads employee Google Sheets, groups rows by client,
 * and presents results for review before creating invoices.
 */
class ScanController extends Controller
{
    /**
     * @var BillingService Orchestrates the billing scan and invoice creation
     */
    protected BillingService $billing;

    /**
     * Constructor — inject the BillingService.
     *
     * @param BillingService $billing Billing orchestration service
     */
    public function __construct(BillingService $billing)
    {
        // Store the billing service reference
        $this->billing = $billing;
    }

    /**
     * Display the scan page (before running a scan).
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Render the scan page — shows the "Run Scan" button
        return view('scan.index');
    }

    /**
     * Execute the billing scan across all active employees.
     * Reads new rows from Google Sheets, validates, and groups by client.
     * Results are stored in the session for the review step.
     *
     * @return \Illuminate\View\View
     */
    public function run()
    {
        // Execute the billing scan via BillingService
        $results = $this->billing->runScan();

        // Store the grouped results in the session for the create-invoices step
        // This allows the user to review before committing
        session(['scan_results' => $results['grouped_by_client']]);

        // Render the scan results page
        return view('scan.results', [
            'groupedByClient'    => $results['grouped_by_client'],     // Rows grouped by client
            'scanLogs'           => $results['scan_logs'],             // Per-employee scan logs
            'errors'             => $results['errors'],                // All scan errors
            'totalRowsScanned'   => $results['total_rows_scanned'],   // Grand total rows read
            'totalRowsCollected' => $results['total_rows_collected'],  // Grand total valid rows
        ]);
    }

    /**
     * Create invoices from the scan results stored in the session.
     * Called after the user reviews the scan results and clicks "Create Invoices".
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createInvoices()
    {
        // Retrieve the scan results from the session
        $groupedByClient = session('scan_results', []);

        // Check if there are any results to process
        if (empty($groupedByClient)) {
            // No results — redirect back with error
            return redirect()
                ->route('scan.index')
                ->with('error', 'No scan results found. Please run a scan first.');
        }

        // Create invoices via BillingService
        $results = $this->billing->createInvoices($groupedByClient);

        // Clear the scan results from the session — they've been processed
        session()->forget('scan_results');

        // Build the success message
        $invoiceCount = count($results['invoices']);
        $errorCount = count($results['errors']);
        $message = "{$invoiceCount} invoice(s) created as drafts on Stripe.";

        // Append error count if any failed
        if ($errorCount > 0) {
            $message .= " {$errorCount} client(s) had errors.";
        }

        // Redirect to the invoice list with the result
        return redirect()
            ->route('invoices.index')
            ->with('success', $message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\ScanLog;
use Illuminate\Http\Request;

/**
 * DashboardController — renders the main overview dashboard.
 * Shows: employee overview, invoice summary, flagged clients,
 * recent scan logs, and system health information.
 */
class DashboardController extends Controller
{
    /**
     * Display the main dashboard page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all active employees with their sheet IDs and scan status
        $employees = Employee::active()->orderBy('name')->get();

        // Get invoice counts by status for the summary cards
        $invoiceCounts = [
            // Count of draft invoices
            'draft' => Invoice::where('status', config('hws.invoice_statuses.draft'))->count(),
            // Count of sent/open invoices
            'sent'  => Invoice::where('status', config('hws.invoice_statuses.sent'))->count(),
            // Count of paid invoices
            'paid'  => Invoice::where('status', config('hws.invoice_statuses.paid'))->count(),
        ];

        // Get total amounts by status for the summary cards
        $invoiceAmounts = [
            // Sum of draft invoice amounts
            'draft' => Invoice::where('status', config('hws.invoice_statuses.draft'))->sum('total_amount'),
            // Sum of sent invoice amounts (outstanding)
            'sent'  => Invoice::where('status', config('hws.invoice_statuses.sent'))->sum('total_amount'),
            // Sum of paid invoice amounts (collected)
            'paid'  => Invoice::where('status', config('hws.invoice_statuses.paid'))->sum('total_amount'),
        ];

        // Get clients with low credit balance that need attention
        $lowCreditClients = Client::lowCredit()->active()->get();

        // Get the 10 most recent scan log entries for the activity feed
        $recentScans = ScanLog::with('employee')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Build system health information
        $systemHealth = [
            // Last scan timestamp (or 'Never' if no scans have run)
            'last_scan' => ScanLog::max('completed_at') ?? 'Never',
            // PHP version running on the server
            'php_version' => phpversion(),
            // Total active employees being tracked
            'active_employees' => $employees->count(),
            // Total active clients in the system
            'active_clients' => Client::active()->count(),
        ];

        // Render the dashboard view with all data
        return view('dashboard.index', [
            'employees'        => $employees,        // Employee list
            'invoiceCounts'    => $invoiceCounts,     // Invoice count summary
            'invoiceAmounts'   => $invoiceAmounts,    // Invoice amount summary
            'lowCreditClients' => $lowCreditClients,  // Flagged clients
            'recentScans'      => $recentScans,       // Recent scan log entries
            'systemHealth'     => $systemHealth,       // System health data
        ]);
    }
}

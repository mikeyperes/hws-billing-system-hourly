<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\ScanLog;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * DashboardController — renders the main overview dashboard.
 * Shows: invoice summary, cloud services stats, employee overview,
 * flagged clients, recent scans, and system health.
 *
 * All stats queries come from GenericService shared methods
 * so Dashboard and module pages always use the same logic.
 */
class DashboardController extends Controller
{
    protected GenericService $generic;

    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Display the main dashboard page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Active employees
        $employees = Employee::active()->orderBy('name')->get();

        // Invoice stats — shared method used here and anywhere else that needs invoice summaries
        $invoiceStats = $this->generic->getInvoiceStats();

        // Cloud stats — shared method used here and in HostingController
        $cloudStats = $this->generic->getCloudStats();

        // Low credit clients
        $lowCreditClients = Client::lowCredit()->active()->get();

        // Recent scan activity (last 10)
        $recentScans = ScanLog::with('employee')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // System health
        $systemHealth = [
            'last_scan'        => ScanLog::max('completed_at') ?? 'Never',
            'php_version'      => phpversion(),
            'active_employees' => $employees->count(),
            'active_clients'   => Client::active()->count(),
        ];

        // Service status (Stripe accounts, Brevo/SMTP)
        $serviceStatus = $this->generic->getServiceStatus();

        return view('dashboard.index', [
            'employees'        => $employees,
            'invoiceCounts'    => $invoiceStats['counts'],
            'invoiceAmounts'   => $invoiceStats['amounts'],
            'lowCreditClients' => $lowCreditClients,
            'recentScans'      => $recentScans,
            'systemHealth'     => $systemHealth,
            'cloudStats'       => $cloudStats,
            'serviceStatus'    => $serviceStatus,
        ]);
    }
}

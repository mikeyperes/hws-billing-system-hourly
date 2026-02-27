<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsService;
use App\Services\StripeService;
use App\Services\EmailService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * DebugController — provides debug module pages for testing system components.
 * Only accessible when config('hws.debug_mode') is true.
 * Each module tests a specific integration or subsystem.
 */
class DebugController extends Controller
{
    /**
     * @var GenericService Shared utility service
     */
    protected GenericService $generic;

    /**
     * Constructor — inject services.
     *
     * @param GenericService $generic Shared utility service
     */
    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Guard — redirect to dashboard if debug mode is off.
     * Single check used by every method instead of repeating the if-block.
     *
     * @return \Illuminate\Http\RedirectResponse|null Redirect if blocked, null if allowed
     */
    private function guardDebugMode()
    {
        if (!config('hws.debug_mode')) {
            return redirect()->route('dashboard')->with('error', 'Debug mode is disabled.');
        }
        return null;
    }

    /**
     * Display the debug modules index page.
     * Lists all available debug modules with their status.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        if ($blocked = $this->guardDebugMode()) return $blocked;

        // Define available debug modules
        $modules = [
            [
                'name'   => 'Google Sheets',
                'desc'   => 'Test service account credentials, sheet access, read/write operations.',
                'route'  => 'debug.google',
                'icon'   => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'status' => file_exists(config('hws.google.credentials_path')) ? 'ready' : 'missing_creds',
            ],
            [
                'name'   => 'Stripe API',
                'desc'   => 'Test API key, list customers, verify webhook connectivity.',
                'route'  => 'debug.stripe',
                'icon'   => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                'status' => config('hws.stripe.secret_key') ? 'ready' : 'missing_key',
            ],
            [
                'name'   => 'Email / SMTP',
                'desc'   => 'Test SMTP connection, send test email, verify Brevo settings.',
                'route'  => 'debug.email',
                'icon'   => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'status' => config('hws.email.smtp_host') ? 'ready' : 'missing_config',
            ],
            [
                'name'   => 'Database',
                'desc'   => 'Test connection, check tables, row counts, run queries.',
                'route'  => 'debug.database',
                'icon'   => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
                'status' => 'ready',
            ],
        ];

        // Render the debug index view
        return view('debug.index', [
            'modules' => $modules,
        ]);
    }

    /**
     * Google Sheets debug module.
     * Tests credentials, service account, and sheet operations.
     *
     * @param Request             $request
     * @param GoogleSheetsService $sheetsService
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function google(Request $request, GoogleSheetsService $sheetsService)
    {
        if ($blocked = $this->guardDebugMode()) return $blocked;

        $results = [];

        // If a test was requested
        if ($request->isMethod('post')) {
            $action = $request->input('action');

            if ($action === 'check_credentials') {
                // Check if credentials file exists and is valid JSON
                $credPath = config('hws.google.credentials_path');
                if (file_exists($credPath)) {
                    $json = json_decode(file_get_contents($credPath), true);
                    if ($json) {
                        $results[] = ['pass' => true, 'test' => 'Credentials file', 'detail' => 'Valid JSON at ' . $credPath];
                        $results[] = ['pass' => true, 'test' => 'Service account email', 'detail' => $json['client_email'] ?? 'not found'];
                        $results[] = ['pass' => true, 'test' => 'Project ID', 'detail' => $json['project_id'] ?? 'not found'];
                    } else {
                        $results[] = ['pass' => false, 'test' => 'Credentials file', 'detail' => 'File exists but is not valid JSON'];
                    }
                } else {
                    $results[] = ['pass' => false, 'test' => 'Credentials file', 'detail' => 'Not found at ' . $credPath];
                }
            }

            if ($action === 'test_sheet' && $request->input('sheet_id')) {
                // Test access to a specific sheet
                $sheetId = $this->generic->extractSheetId($request->input('sheet_id'));
                $validation = $sheetsService->validateSheetAccess($sheetId);
                $results[] = [
                    'pass'   => $validation['success'],
                    'test'   => 'Sheet access (' . $sheetId . ')',
                    'detail' => $validation['message'],
                ];
            }
        }

        return view('debug.google', ['results' => $results]);
    }

    /**
     * Stripe debug module.
     * Tests API key and basic operations.
     *
     * @param Request       $request
     * @param StripeService $stripeService
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function stripe(Request $request, StripeService $stripeService)
    {
        if ($blocked = $this->guardDebugMode()) return $blocked;

        $results = [];

        if ($request->isMethod('post')) {
            $action = $request->input('action');

            if ($action === 'test_key') {
                // Test Stripe API key by listing customers (limit 1)
                try {
                    $key = config('hws.stripe.secret_key');
                    if (!$key) {
                        $results[] = ['pass' => false, 'test' => 'API Key', 'detail' => 'STRIPE_SECRET_KEY not set in .env'];
                    } else {
                        $keyPrefix = substr($key, 0, 7) . '...' . substr($key, -4);
                        $results[] = ['pass' => true, 'test' => 'API Key', 'detail' => 'Key found: ' . $keyPrefix];

                        // Try an API call
                        \Stripe\Stripe::setApiKey($key);
                        $customers = \Stripe\Customer::all(['limit' => 1]);
                        $results[] = ['pass' => true, 'test' => 'API Connection', 'detail' => 'Successfully connected. Total customers accessible.'];
                    }
                } catch (\Exception $e) {
                    $results[] = ['pass' => false, 'test' => 'API Connection', 'detail' => $e->getMessage()];
                }
            }
        }

        return view('debug.stripe', ['results' => $results]);
    }

    /**
     * Email debug module.
     * Tests SMTP configuration and sends test email.
     *
     * @param Request      $request
     * @param EmailService $emailService
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function email(Request $request, EmailService $emailService)
    {
        if ($blocked = $this->guardDebugMode()) return $blocked;

        $results = [];

        if ($request->isMethod('post')) {
            $action = $request->input('action');

            if ($action === 'check_config') {
                // Check SMTP configuration values
                $host = config('hws.email.smtp_host');
                $port = config('hws.email.smtp_port');
                $user = config('hws.email.smtp_username');
                $from = config('hws.email.from_address');

                $results[] = ['pass' => (bool)$host, 'test' => 'SMTP Host', 'detail' => $host ?: 'NOT SET'];
                $results[] = ['pass' => (bool)$port, 'test' => 'SMTP Port', 'detail' => $port ?: 'NOT SET'];
                $results[] = ['pass' => (bool)$user, 'test' => 'SMTP Username', 'detail' => $user ?: 'NOT SET'];
                $results[] = ['pass' => (bool)$from, 'test' => 'From Address', 'detail' => $from ?: 'NOT SET'];
            }

            if ($action === 'send_test' && $request->input('to_email')) {
                // Send a test email
                try {
                    $result = $emailService->send(
                        $request->input('to_email'),
                        'Debug Test',
                        'Debug Test Email — ' . config('hws.app_name'),
                        '<h2>Debug Test Email</h2><p>This email was sent from the debug module at ' . now() . '</p>'
                    );
                    $results[] = [
                        'pass'   => $result['success'],
                        'test'   => 'Send test email',
                        'detail' => $result['success'] ? 'Email sent successfully' : ($result['error'] ?? 'Unknown error'),
                    ];
                } catch (\Exception $e) {
                    $results[] = ['pass' => false, 'test' => 'Send test email', 'detail' => $e->getMessage()];
                }
            }
        }

        return view('debug.email', ['results' => $results]);
    }

    /**
     * Database debug module.
     * Tests connection and shows table info.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function database(Request $request)
    {
        if ($blocked = $this->guardDebugMode()) return $blocked;

        $results = [];
        $tables = [];

        if ($request->isMethod('post')) {
            $action = $request->input('action');

            if ($action === 'test_connection') {
                try {
                    // Test the database connection
                    \DB::connection()->getPdo();
                    $results[] = ['pass' => true, 'test' => 'Database connection', 'detail' => 'Connected to ' . config('database.connections.mysql.database')];

                    // Get all tables and row counts
                    $rawTables = \DB::select('SHOW TABLES');
                    $dbName = config('database.connections.mysql.database');
                    $key = 'Tables_in_' . $dbName;

                    foreach ($rawTables as $t) {
                        $tableName = $t->$key ?? '';
                        if ($tableName) {
                            $count = \DB::table($tableName)->count();
                            $tables[] = ['name' => $tableName, 'rows' => $count];
                        }
                    }

                    $results[] = ['pass' => true, 'test' => 'Tables found', 'detail' => count($tables) . ' tables'];
                } catch (\Exception $e) {
                    $results[] = ['pass' => false, 'test' => 'Database connection', 'detail' => $e->getMessage()];
                }
            }
        }

        return view('debug.database', ['results' => $results, 'tables' => $tables]);
    }
}

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\InvoiceGeneratorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| All routes are behind the 'auth' middleware.
| Only authenticated users can access any page.
| Auth routes (login/logout) are the only public routes.
|--------------------------------------------------------------------------
*/

// ── Public: redirect root to login or dashboard ──
Route::get('/', function () {
    // If authenticated, go to dashboard; otherwise go to login
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// ── Auth Routes (public) ──
// Show the login form
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
// Process login attempt
Route::post('/login', [LoginController::class, 'login']);
// Logout (requires auth to prevent CSRF issues)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Forgot Password Routes (public) ──
// Show the forgot password form
Route::get('/forgot-password', [ForgotPasswordController::class, 'showRequestForm'])->name('password.request');
// Send the reset link email
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
// Show the reset form (user clicks link in email)
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
// Process the password reset
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update');

// ── All authenticated routes ──
Route::middleware(['auth'])->group(function () {

    // ── Dashboard ──
    // Main overview page
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Clients ──
    // List all clients
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    // Show the Stripe import tool
    Route::get('/clients/import', [ClientController::class, 'showImport'])->name('clients.import');
    // Process the Stripe import
    Route::post('/clients/import', [ClientController::class, 'processImport'])->name('clients.import.process');
    // Edit a specific client
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    // Update a specific client
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    // Adjust client credit balance
    Route::post('/clients/{client}/credits', [ClientController::class, 'adjustCredits'])->name('clients.credits');

    // ── Employees ──
    // List all employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    // Show create employee form
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    // Store a new employee
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    // Edit a specific employee
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    // Update a specific employee
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    // Validate employee's Google Sheet access
    Route::post('/employees/{employee}/validate', [EmployeeController::class, 'validateSheet'])->name('employees.validate');

    // ── Billing Scan ──
    // Show the scan page
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    // Execute the billing scan
    Route::post('/scan/run', [ScanController::class, 'run'])->name('scan.run');
    // Create invoices from scan results
    Route::post('/scan/create-invoices', [ScanController::class, 'createInvoices'])->name('scan.create-invoices');

    // ── Invoices ──
    // List all invoices (with optional status filter)
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    // View line items for a specific invoice
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    // Mark an invoice as billed (updates sheets)
    Route::post('/invoices/{invoice}/mark-billed', [InvoiceController::class, 'markBilled'])->name('invoices.mark-billed');
    // Reverse billing for an invoice
    Route::post('/invoices/{invoice}/reverse', [InvoiceController::class, 'reverseBilling'])->name('invoices.reverse');
    // Refresh payment status from Stripe
    Route::post('/invoices/{invoice}/refresh', [InvoiceController::class, 'refreshStatus'])->name('invoices.refresh');
    // Refresh ALL unpaid invoices from Stripe
    Route::post('/invoices/refresh-all', [InvoiceController::class, 'refreshAllUnpaid'])->name('invoices.refresh-all');
    // Show email compose form for an invoice
    Route::get('/invoices/{invoice}/email', [InvoiceController::class, 'showEmail'])->name('invoices.email');
    // Send email for an invoice
    Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'sendEmail'])->name('invoices.send-email');
    // Finalize and send via Stripe
    Route::post('/invoices/{invoice}/send-stripe', [InvoiceController::class, 'sendViaStripe'])->name('invoices.send-stripe');

    // ── Email Templates ──
    // List all templates
    Route::get('/emails', [EmailTemplateController::class, 'index'])->name('emails.index');
    // Show create template form
    Route::get('/emails/create', [EmailTemplateController::class, 'create'])->name('emails.create');
    // Store a new template
    Route::post('/emails', [EmailTemplateController::class, 'store'])->name('emails.store');
    // Edit a specific template
    Route::get('/emails/{email}/edit', [EmailTemplateController::class, 'edit'])->name('emails.edit');
    // Update a specific template
    Route::put('/emails/{email}', [EmailTemplateController::class, 'update'])->name('emails.update');
    // Set a template as primary for its use case
    Route::post('/emails/{email}/primary', [EmailTemplateController::class, 'makePrimary'])->name('emails.primary');
    // Send a test email
    Route::post('/emails/{email}/test', [EmailTemplateController::class, 'testSend'])->name('emails.test');
    // Delete a template
    Route::delete('/emails/{email}', [EmailTemplateController::class, 'destroy'])->name('emails.destroy');

    // ── Lists ──
    // List management page
    Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
    // Add a new list item
    Route::post('/lists', [ListController::class, 'store'])->name('lists.store');
    // Toggle active/inactive status
    Route::post('/lists/{list}/toggle', [ListController::class, 'toggle'])->name('lists.toggle');
    // Delete a list item
    Route::delete('/lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');

    // ── Hexa Cloud Services ──
    // Cloud services dashboard
    Route::get('/hosting', [HostingController::class, 'index'])->name('hosting.index');
    // WHM Servers
    Route::get('/hosting/servers', [HostingController::class, 'servers'])->name('hosting.servers');
    Route::get('/hosting/servers/create', [HostingController::class, 'createServer'])->name('hosting.server.create');
    Route::post('/hosting/servers', [HostingController::class, 'storeServer'])->name('hosting.server.store');
    Route::get('/hosting/servers/{server}/edit', [HostingController::class, 'editServer'])->name('hosting.server.edit');
    Route::put('/hosting/servers/{server}', [HostingController::class, 'updateServer'])->name('hosting.server.update');
    // Hosting Accounts
    Route::get('/hosting/accounts', [HostingController::class, 'accounts'])->name('hosting.accounts');
    Route::get('/hosting/accounts/{account}/edit', [HostingController::class, 'editAccount'])->name('hosting.account.edit');
    Route::put('/hosting/accounts/{account}', [HostingController::class, 'updateAccount'])->name('hosting.account.update');
    // Hosting Subscriptions
    Route::post('/hosting/accounts/{account}/subscriptions', [HostingController::class, 'addSubscription'])->name('hosting.subscription.add');
    Route::delete('/hosting/subscriptions/{subscription}', [HostingController::class, 'removeSubscription'])->name('hosting.subscription.remove');

    // ── Invoice Generator ──
    // Quick invoice parameter tool
    Route::get('/invoice-generator', [InvoiceGeneratorController::class, 'index'])->name('invoice-generator.index');
    Route::post('/invoice-generator', [InvoiceGeneratorController::class, 'generate'])->name('invoice-generator.generate');

    // ── Settings ──
    // Settings page
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    // Update settings
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    // Send test email
    Route::post('/settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');

    // ── System Info ──
    // System info, git details, command reference
    Route::get('/info', [InfoController::class, 'index'])->name('info.index');

    // ── Debug Modules (only accessible when debug_mode is true) ──
    // Debug modules index
    Route::get('/debug', [DebugController::class, 'index'])->name('debug.index');
    // Google Sheets debug
    Route::match(['get', 'post'], '/debug/google', [DebugController::class, 'google'])->name('debug.google');
    // Stripe debug
    Route::match(['get', 'post'], '/debug/stripe', [DebugController::class, 'stripe'])->name('debug.stripe');
    // Email debug
    Route::match(['get', 'post'], '/debug/email', [DebugController::class, 'email'])->name('debug.email');
    // Database debug
    Route::match(['get', 'post'], '/debug/database', [DebugController::class, 'database'])->name('debug.database');
});

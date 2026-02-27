<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\GoogleSheetsService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * EmployeeController — handles employee CRUD with separate Google Sheet validation.
 * Employees are saved without sheet validation.
 * A separate "Validate" action checks sheet access, headers, and permissions.
 */
class EmployeeController extends Controller
{
    /**
     * @var GoogleSheetsService Google Sheets API service for validation
     */
    protected GoogleSheetsService $sheetsService;

    /**
     * @var GenericService Shared utility service
     */
    protected GenericService $generic;

    /**
     * Constructor — inject required services.
     *
     * @param GoogleSheetsService $sheetsService Google Sheets API service
     * @param GenericService      $generic       Shared utility service
     */
    public function __construct(GoogleSheetsService $sheetsService, GenericService $generic)
    {
        // Store service references
        $this->sheetsService = $sheetsService;
        $this->generic = $generic;
    }

    /**
     * Display the employee list page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all employees ordered by name
        $employees = Employee::orderBy('name')->paginate(config('hws.per_page'));

        // Render the employee list view
        return view('employees.index', [
            'employees' => $employees,  // Paginated employee collection
        ]);
    }

    /**
     * Display the create employee form.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // Render the create form
        return view('employees.create');
    }

    /**
     * Store a new employee record.
     * Saves immediately without Google Sheet validation.
     * Validation is done separately via the Validate button on the edit page.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'name'            => 'required|string|max:255',  // Employee name
            'google_sheet_id' => 'required|string|max:500',  // Sheet URL or ID
        ]);

        // Extract the sheet ID from the URL (if a full URL was provided)
        $sheetId = $this->generic->extractSheetId($validated['google_sheet_id']);

        // Create the employee record — no sheet validation, just save
        $employee = Employee::create([
            'name'            => $validated['name'],  // Employee name
            'google_sheet_id' => $sheetId,            // Extracted sheet ID
            'is_active'       => true,                // Active by default
        ]);

        // Redirect to the edit page so they can validate the sheet
        return redirect()
            ->route('employees.edit', $employee)
            ->with('success', 'Employee "' . $employee->name . '" created. Use the Validate Sheet button to check access.');
    }

    /**
     * Display the employee edit form.
     *
     * @param Employee $employee Route model binding
     * @return \Illuminate\View\View
     */
    public function edit(Employee $employee)
    {
        // Get recent scan logs for this employee (last 5)
        $recentScans = $employee->scanLogs()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Render the edit form
        return view('employees.edit', [
            'employee'    => $employee,    // The employee being edited
            'recentScans' => $recentScans, // Recent scan history
        ]);
    }

    /**
     * Update an existing employee record.
     * Saves immediately without Google Sheet validation.
     *
     * @param Request  $request
     * @param Employee $employee Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Employee $employee)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',     // Employee name
            'google_sheet_id'        => 'required|string|max:500',     // Sheet URL or ID
            'scan_start_primary_key' => 'nullable|integer|min:0',      // Manual cursor override
            'is_active'              => 'boolean',                      // Active toggle
        ]);

        // Extract the sheet ID from the URL
        $sheetId = $this->generic->extractSheetId($validated['google_sheet_id']);

        // Update the employee record — no sheet validation, just save
        $employee->update([
            'name'                   => $validated['name'],                             // Name
            'google_sheet_id'        => $sheetId,                                       // Sheet ID
            'scan_start_primary_key' => $validated['scan_start_primary_key'] ?? $employee->scan_start_primary_key, // Cursor
            'is_active'              => $request->has('is_active'),                      // Active toggle
        ]);

        // Redirect back to the edit page with success message
        return redirect()
            ->route('employees.edit', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Validate an employee's Google Sheet access.
     * Runs a series of checks and returns results to the edit page.
     *
     * Checks performed:
     * 1. Google credentials file exists on disk
     * 2. Service account can access the sheet (read permission)
     * 3. Sheet has all required column headers in Row 1
     * 4. Reports the service account email for sharing reference
     *
     * @param Employee $employee Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validateSheet(Employee $employee)
    {
        // Array to collect validation results — each item has: pass (bool), check (string), message (string)
        $results = [];

        // ── Check 1: Google credentials file exists ──
        $credPath = config('hws.google.credentials_path');
        if (file_exists($credPath)) {
            // Credentials file found
            $results[] = [
                'pass'    => true,
                'check'   => 'Google Credentials File',
                'message' => 'Found at ' . $credPath,
            ];
        } else {
            // Credentials file missing — cannot proceed
            $results[] = [
                'pass'    => false,
                'check'   => 'Google Credentials File',
                'message' => 'NOT FOUND at ' . $credPath . '. Upload your Google service account JSON key file.',
            ];
            // Return early — nothing else will work without credentials
            return redirect()
                ->route('employees.edit', $employee)
                ->with('validation_results', $results);
        }

        // ── Check 2: Service account can access the sheet + headers are correct ──
        $validation = $this->sheetsService->validateSheetAccess($employee->google_sheet_id);
        if ($validation['success']) {
            // Sheet is accessible and headers are correct
            $results[] = [
                'pass'    => true,
                'check'   => 'Sheet Access',
                'message' => 'Service account can read the sheet.',
            ];
            $results[] = [
                'pass'    => true,
                'check'   => 'Column Headers',
                'message' => 'All required headers found: ' . implode(', ', $validation['headers'] ?? []),
            ];
        } else {
            // Access or header check failed — report the specific error
            $results[] = [
                'pass'    => false,
                'check'   => 'Sheet Access / Headers',
                'message' => $validation['message'],
            ];
        }

        // ── Check 3: Try to read data rows ──
        try {
            // Read all rows from the sheet starting from PK 0 (all rows)
            $readResult = $this->sheetsService->readRows($employee->google_sheet_id, 0);
            if ($readResult['success']) {
                $rowCount = count($readResult['rows'] ?? []);
                if ($rowCount > 0) {
                    $results[] = [
                        'pass'    => true,
                        'check'   => 'Data Rows',
                        'message' => $rowCount . ' data rows found in sheet.',
                    ];
                } else {
                    $results[] = [
                        'pass'    => false,
                        'check'   => 'Data Rows',
                        'message' => 'No data rows found — sheet is empty (besides header row).',
                    ];
                }
            } else {
                $results[] = [
                    'pass'    => false,
                    'check'   => 'Data Rows',
                    'message' => 'Could not read rows: ' . ($readResult['error'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            // Error reading rows — report it
            $results[] = [
                'pass'    => false,
                'check'   => 'Data Rows',
                'message' => 'Error reading rows: ' . $e->getMessage(),
            ];
        }

        // ── Info: Service account email ──
        $serviceEmail = config('hws.google.service_account_email', '(not configured)');
        $results[] = [
            'pass'    => true,
            'check'   => 'Service Account Email',
            'message' => $serviceEmail ?: '(not configured — set GOOGLE_SERVICE_ACCOUNT_EMAIL in .env)',
        ];

        // ── Info: Sheet URL ──
        $results[] = [
            'pass'    => true,
            'check'   => 'Sheet URL',
            'message' => 'https://docs.google.com/spreadsheets/d/' . $employee->google_sheet_id,
        ];

        // Redirect back to edit page with all validation results
        return redirect()
            ->route('employees.edit', $employee)
            ->with('validation_results', $results);
    }
}

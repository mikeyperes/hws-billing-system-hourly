<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\GoogleSheetsService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * EmployeeController — handles employee CRUD and Google Sheet validation.
 * Each employee has a Google Sheet for time tracking.
 * Sheet access is validated when adding or updating the sheet ID.
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
     * Validates the Google Sheet is accessible before saving.
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

        // Validate that the service account can access the sheet
        $validation = $this->sheetsService->validateSheetAccess($sheetId);

        // If validation failed, redirect back with the error
        if (!$validation['success']) {
            // Redirect back to the form with the validation error
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Sheet validation failed: ' . $validation['message']);
        }

        // Create the employee record
        $employee = Employee::create([
            'name'            => $validated['name'],  // Employee name
            'google_sheet_id' => $sheetId,            // Extracted sheet ID
            'is_active'       => true,                // Active by default
        ]);

        // Redirect to the employee list with success message
        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee "' . $employee->name . '" created successfully.');
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
     * Re-validates the Google Sheet if the sheet ID changed.
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

        // Check if the sheet ID changed — if so, re-validate access
        if ($sheetId !== $employee->google_sheet_id) {
            // Validate the new sheet
            $validation = $this->sheetsService->validateSheetAccess($sheetId);

            // If validation failed, redirect back with the error
            if (!$validation['success']) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Sheet validation failed: ' . $validation['message']);
            }
        }

        // Update the employee record
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
}

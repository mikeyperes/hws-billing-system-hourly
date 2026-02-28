<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\GoogleSheetsService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * EmployeeController — handles employee CRUD with separate Google Sheet validation.
 * Employees can be saved without a Google Sheet ID (added later).
 * A separate "Validate" action runs a detailed checklist on the sheet.
 */
class EmployeeController extends Controller
{
    protected GoogleSheetsService $sheetsService;
    protected GenericService $generic;

    public function __construct(GoogleSheetsService $sheetsService, GenericService $generic)
    {
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
        $employees = Employee::orderBy('name')->paginate(config('hws.per_page'));
        return view('employees.index', ['employees' => $employees]);
    }

    /**
     * Display the create employee form.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('employees.create');
    }

    /**
     * Store a new employee record.
     * Google Sheet ID is optional — can be added later.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'google_sheet_id' => 'nullable|string|max:500',
        ]);

        // Extract sheet ID from URL if provided
        $sheetId = null;
        if (!empty($validated['google_sheet_id'])) {
            $sheetId = $this->generic->extractSheetId($validated['google_sheet_id']);
        }

        $employee = Employee::create([
            'name'            => $validated['name'],
            'google_sheet_id' => $sheetId,
            'is_active'       => true,
        ]);

        $message = 'Employee "' . $employee->name . '" created.';
        if ($sheetId) {
            $message .= ' Use the Validate Sheet button to check access.';
        } else {
            $message .= ' Add a Google Sheet ID when ready.';
        }

        return redirect()->route('employees.edit', $employee)->with('success', $message);
    }

    /**
     * Display the employee edit form.
     *
     * @param Employee $employee Route model binding
     * @return \Illuminate\View\View
     */
    public function edit(Employee $employee)
    {
        $recentScans = $employee->scanLogs()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('employees.edit', [
            'employee'    => $employee,
            'recentScans' => $recentScans,
        ]);
    }

    /**
     * Update an existing employee record.
     * Tracks previous scan cursor value before overwriting.
     *
     * @param Request  $request
     * @param Employee $employee
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'google_sheet_id'        => 'nullable|string|max:500',
            'scan_start_primary_key' => 'nullable|integer|min:0',
            'is_active'              => 'boolean',
        ]);

        // Extract sheet ID from URL if provided
        $sheetId = null;
        if (!empty($validated['google_sheet_id'])) {
            $sheetId = $this->generic->extractSheetId($validated['google_sheet_id']);
        }

        // Track previous cursor before updating
        $newCursor = $validated['scan_start_primary_key'] ?? $employee->scan_start_primary_key;
        $previousCursor = $employee->scan_start_primary_key;

        $employee->update([
            'name'                            => $validated['name'],
            'google_sheet_id'                 => $sheetId,
            'scan_start_primary_key'          => $newCursor,
            'previous_scan_start_primary_key' => $previousCursor,
            'is_active'                       => $request->has('is_active'),
        ]);

        return redirect()->route('employees.edit', $employee)->with('success', 'Employee updated successfully.');
    }

    /**
     * Run detailed validation on an employee's Google Sheet.
     * Returns a comprehensive checklist with pass/fail results.
     *
     * Checks performed:
     * 1. Google credentials file exists
     * 2. Sheet is accessible (readable) by service account
     * 3. Sheet is writable by service account
     * 4. Each required column header checked individually
     * 5. Detected starting point (scan cursor)
     * 6. Row preview from starting point to end
     * 7. Each row checked for readability (non-empty)
     * 8. Flag any unreadable rows that would be skipped
     *
     * @param Employee $employee
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validateSheet(Employee $employee)
    {
        $results = [];

        // ── Pre-check: Sheet ID configured ──
        if (empty($employee->google_sheet_id)) {
            $results[] = [
                'pass'    => false,
                'check'   => 'Google Sheet ID',
                'message' => 'No Google Sheet ID configured for this employee. Add one first.',
            ];
            return redirect()->route('employees.edit', $employee)
                ->with('validation_results', $results);
        }

        // ── Check 1: Google credentials file exists ──
        $credPath = config('hws.google.credentials_path');
        if (file_exists($credPath)) {
            $results[] = [
                'pass'    => true,
                'check'   => 'Google Credentials File',
                'message' => 'Found at ' . $credPath,
            ];
        } else {
            $results[] = [
                'pass'    => false,
                'check'   => 'Google Credentials File',
                'message' => 'NOT FOUND at ' . $credPath . '. Upload your Google service account JSON key file.',
            ];
            return redirect()->route('employees.edit', $employee)
                ->with('validation_results', $results);
        }

        // ── Check 2: Sheet is accessible (read test) ──
        $validation = $this->sheetsService->validateSheetAccess($employee->google_sheet_id);
        if ($validation['success']) {
            $results[] = [
                'pass'    => true,
                'check'   => 'Sheet Readable',
                'message' => 'Service account can read the sheet.',
            ];
        } else {
            $results[] = [
                'pass'    => false,
                'check'   => 'Sheet Readable',
                'message' => $validation['message'],
            ];
            return redirect()->route('employees.edit', $employee)
                ->with('validation_results', $results);
        }

        // ── Check 3: Sheet is writable (attempt write test) ──
        $writeTestResult = $this->sheetsService->testWriteAccess($employee->google_sheet_id);
        $results[] = [
            'pass'    => $writeTestResult['success'],
            'check'   => 'Sheet Writable',
            'message' => $writeTestResult['message'],
        ];

        // ── Check 4: Each required column checked individually ──
        $actualHeaders = $validation['headers'] ?? [];
        $expectedColumns = config('hws.sheet_columns');
        foreach ($expectedColumns as $key => $columnName) {
            $found = in_array($columnName, $actualHeaders);
            $results[] = [
                'pass'    => $found,
                'check'   => 'Column: ' . $columnName,
                'message' => $found
                    ? 'Found in header row (position: ' . (array_search($columnName, $actualHeaders) + 1) . ')'
                    : 'MISSING — required column "' . $columnName . '" not found in header row.',
            ];
        }

        // ── Check 5: Detected starting point ──
        $cursor = $employee->scan_start_primary_key;
        $results[] = [
            'pass'    => true,
            'check'   => 'Scan Cursor (Starting Point)',
            'message' => 'Current cursor: ' . $cursor . '. Will read rows with primary_key > ' . $cursor . '.',
        ];

        // ── Check 6 & 7: Read rows from cursor, validate each ──
        try {
            $readResult = $this->sheetsService->readRows($employee->google_sheet_id, $cursor);
            if ($readResult['success']) {
                $rows = $readResult['rows'] ?? [];
                $rowCount = count($rows);

                $results[] = [
                    'pass'    => $rowCount > 0,
                    'check'   => 'Data Rows From Cursor',
                    'message' => $rowCount > 0
                        ? $rowCount . ' rows found after cursor position ' . $cursor . '.'
                        : 'No data rows found after cursor position ' . $cursor . '. Sheet may be fully scanned.',
                ];

                // Check each row for readability
                $readableCount = 0;
                $unreadableRows = [];
                $rowPreview = [];
                $colPrimaryKey = config('hws.sheet_columns.primary_key');
                $colClient = config('hws.sheet_columns.client');
                $colBilledStatus = config('hws.sheet_columns.billed_status');
                $colTime = config('hws.sheet_columns.time');
                $colDescription = config('hws.sheet_columns.description');
                $pendingStatus = config('hws.billed_status.pending');

                foreach ($rows as $row) {
                    $pk = $row[$colPrimaryKey] ?? '';
                    $clientName = trim($row[$colClient] ?? '');
                    $status = trim($row[$colBilledStatus] ?? '');
                    $time = trim($row[$colTime] ?? '');
                    $desc = trim($row[$colDescription] ?? '');
                    $sheetRow = $row['_sheet_row_number'] ?? '?';

                    // A row is readable if it has a primary key and at least some content
                    $hasContent = !empty($pk) && (!empty($clientName) || !empty($desc) || !empty($time));

                    if ($hasContent) {
                        $readableCount++;
                    } else {
                        $unreadableRows[] = 'Row ' . $sheetRow . ' (PK: ' . ($pk ?: 'empty') . ')';
                    }

                    // Build preview (first 20 rows)
                    if (count($rowPreview) < 20) {
                        $isPending = strtolower($status) === $pendingStatus;
                        $rowPreview[] = [
                            'sheet_row'   => $sheetRow,
                            'primary_key' => $pk,
                            'client'      => $clientName ?: '(empty)',
                            'time'        => $time ?: '(empty)',
                            'status'      => $status ?: '(empty)',
                            'description' => mb_substr($desc, 0, 50) . (mb_strlen($desc) > 50 ? '...' : ''),
                            'is_pending'  => $isPending,
                            'readable'    => $hasContent,
                        ];
                    }
                }

                // Readable rows result
                $results[] = [
                    'pass'    => $readableCount === $rowCount || $rowCount === 0,
                    'check'   => 'Row Readability',
                    'message' => $readableCount . '/' . $rowCount . ' rows are readable.'
                        . (count($unreadableRows) > 0
                            ? ' Unreadable: ' . implode(', ', array_slice($unreadableRows, 0, 10))
                            . (count($unreadableRows) > 10 ? ' (and ' . (count($unreadableRows) - 10) . ' more)' : '')
                            : ''),
                ];

                // Unreadable rows warning
                if (count($unreadableRows) > 0) {
                    $results[] = [
                        'pass'    => false,
                        'check'   => 'Unreadable Row Warning',
                        'message' => count($unreadableRows) . ' row(s) have no readable content. These could cause billable items to be skipped during scan. Fix them in the sheet.',
                    ];
                }

            } else {
                $results[] = [
                    'pass'    => false,
                    'check'   => 'Data Rows',
                    'message' => 'Could not read rows: ' . ($readResult['error'] ?? 'Unknown error'),
                ];
                $rowPreview = [];
            }
        } catch (\Exception $e) {
            $results[] = [
                'pass'    => false,
                'check'   => 'Data Rows',
                'message' => 'Error reading rows: ' . $e->getMessage(),
            ];
            $rowPreview = [];
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

        return redirect()->route('employees.edit', $employee)
            ->with('validation_results', $results)
            ->with('row_preview', $rowPreview ?? []);
    }

}

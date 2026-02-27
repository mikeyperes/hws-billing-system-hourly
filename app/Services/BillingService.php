<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\ScanLog;
use Illuminate\Support\Facades\DB;

/**
 * BillingService — orchestrates the entire billing workflow.
 * Coordinates between GoogleSheetsService and StripeService to:
 * 1. Scan employee sheets for new billable rows
 * 2. Group rows by client
 * 3. Create Stripe draft invoices
 * 4. Mark rows as billed and update cursors
 * 5. Handle credit deduction for hourly_credits clients
 * 6. Reverse billing when corrections are needed
 */
class BillingService
{
    /**
     * @var GoogleSheetsService Service for reading/writing Google Sheets
     */
    protected GoogleSheetsService $sheetsService;

    /**
     * @var StripeService Service for creating/managing Stripe invoices
     */
    protected StripeService $stripeService;

    /**
     * @var GenericService Shared utility service for logging and formatting
     */
    protected GenericService $generic;

    /**
     * Constructor — inject all required services.
     *
     * @param GoogleSheetsService $sheetsService Google Sheets API service
     * @param StripeService       $stripeService Stripe API service
     * @param GenericService      $generic       Shared utility service
     */
    public function __construct(
        GoogleSheetsService $sheetsService,
        StripeService $stripeService,
        GenericService $generic
    ) {
        // Store references to injected services
        $this->sheetsService = $sheetsService;
        $this->stripeService = $stripeService;
        $this->generic = $generic;
    }

    /**
     * Run the billing scan across all active employees.
     * Reads new rows from each employee's Google Sheet, validates them,
     * and groups them by client for review before invoice creation.
     *
     * @return array{
     *   grouped_by_client: array,
     *   scan_logs: array,
     *   errors: array,
     *   total_rows_scanned: int,
     *   total_rows_collected: int
     * } Scan results organized for display
     */
    public function runScan(): array
    {
        // Get all active employees to scan
        $employees = Employee::active()->get();

        // Initialize result containers
        $groupedByClient = []; // Rows grouped by client name
        $scanLogs = [];        // Per-employee scan log entries
        $allErrors = [];       // All errors across all employees
        $totalScanned = 0;     // Running total of rows scanned
        $totalCollected = 0;   // Running total of valid rows collected

        // Get the expected column names from config
        $colPrimaryKey = config('hws.sheet_columns.primary_key');
        $colBilledStatus = config('hws.sheet_columns.billed_status');
        $colClient = config('hws.sheet_columns.client');
        $colTime = config('hws.sheet_columns.time');
        $colDate = config('hws.sheet_columns.date');
        $colDescription = config('hws.sheet_columns.description');
        $colDomain = config('hws.sheet_columns.domain');
        $pendingStatus = config('hws.billed_status.pending');

        // Get list of valid client names for matching
        $validClients = Client::active()->pluck('name')->toArray();

        // Loop through each active employee
        foreach ($employees as $employee) {
            // Create a scan log entry for this employee
            $scanLog = ScanLog::create([
                'employee_id' => $employee->id,          // Which employee
                'scan_type' => 'billing',                // Type of scan
                'started_at' => now(),                   // Start timestamp
                'status' => 'running',                   // Initial status
            ]);

            // Track errors for this specific employee
            $employeeErrors = [];
            // Track collected rows for this employee
            $employeeCollected = 0;

            // Read rows from the employee's sheet starting after their scan cursor
            $result = $this->sheetsService->readRows(
                $employee->google_sheet_id,        // Sheet ID
                $employee->scan_start_primary_key  // Start after this primary_key
            );

            // Check if the sheet read was successful
            if (!$result['success']) {
                // Record the sheet read failure as an error
                $employeeErrors[] = [
                    'type' => 'sheet_read_error',         // Error category
                    'message' => $result['error'] ?? 'Unknown error reading sheet', // Error detail
                ];

                // Update the scan log with failure status
                $scanLog->update([
                    'status' => 'failed',                  // Mark as failed
                    'errors' => $employeeErrors,           // Store errors
                    'completed_at' => now(),                // End timestamp
                ]);

                // Add to the overall scan logs
                $scanLogs[] = $scanLog;
                // Skip to the next employee
                continue;
            }

            // Get the rows returned from the sheet
            $rows = $result['rows'];
            // Count total rows scanned for this employee
            $rowsScanned = count($rows);
            // Add to the running total
            $totalScanned += $rowsScanned;

            // Process each row from the sheet
            foreach ($rows as $row) {
                // ── Validation: Check billed_status is pending ──
                $billedStatus = $row[$colBilledStatus] ?? '';
                if (strtolower(trim($billedStatus)) !== $pendingStatus) {
                    // Skip rows that aren't pending (already billed or invalid)
                    continue;
                }

                // ── Validation: Check client name matches a known client ──
                $clientName = trim($row[$colClient] ?? '');
                if (empty($clientName)) {
                    // Record missing client name error
                    $employeeErrors[] = [
                        'type' => 'missing_client',
                        'row' => $row['_sheet_row_number'] ?? 'unknown',
                        'message' => 'Client name is empty',
                    ];
                    // Skip this row
                    continue;
                }

                // Check if the client name exists in our system
                if (!in_array($clientName, $validClients)) {
                    // Record unmatched client name error
                    $employeeErrors[] = [
                        'type' => 'unmatched_client',
                        'row' => $row['_sheet_row_number'] ?? 'unknown',
                        'client' => $clientName,
                        'message' => 'Client "' . $clientName . '" not found in system',
                    ];
                    // Skip this row
                    continue;
                }

                // ── Validation: Check time is numeric ──
                $timeValue = $row[$colTime] ?? '';
                if (!is_numeric($timeValue) || (int) $timeValue <= 0) {
                    // Record invalid time error
                    $employeeErrors[] = [
                        'type' => 'invalid_time',
                        'row' => $row['_sheet_row_number'] ?? 'unknown',
                        'value' => $timeValue,
                        'message' => 'Time value is not a valid positive number',
                    ];
                    // Skip this row
                    continue;
                }

                // ── Validation: Check date is present ──
                $dateValue = trim($row[$colDate] ?? '');
                if (empty($dateValue)) {
                    // Record missing date error
                    $employeeErrors[] = [
                        'type' => 'missing_date',
                        'row' => $row['_sheet_row_number'] ?? 'unknown',
                        'message' => 'Date is empty',
                    ];
                    // Skip this row
                    continue;
                }

                // ── Row passed all validation — add to grouped results ──
                // Initialize the client group if this is the first row for this client
                if (!isset($groupedByClient[$clientName])) {
                    $groupedByClient[$clientName] = [];
                }

                // Add the row to the client's group with all relevant data
                $groupedByClient[$clientName][] = [
                    'employee_id'      => $employee->id,                    // Which employee
                    'employee_name'    => $employee->name,                  // Employee name for display
                    'primary_key'      => (int) $row[$colPrimaryKey],       // Sheet primary_key
                    'date'             => $dateValue,                       // Work date
                    'time_minutes'     => (int) $timeValue,                 // Duration in minutes
                    'description'      => $row[$colDescription] ?? '',      // Work description
                    'client_name'      => $clientName,                      // Client name
                    'domain'           => $row[$colDomain] ?? '',           // Domain (stored, not processed)
                    'sheet_row_number' => $row['_sheet_row_number'] ?? null, // Google Sheet row number
                ];

                // Increment collected counter for this employee
                $employeeCollected++;
            }

            // Add employee's collected rows to the running total
            $totalCollected += $employeeCollected;
            // Add employee's errors to the overall error list
            $allErrors = array_merge($allErrors, $employeeErrors);

            // Update the scan log with final results
            $scanLog->update([
                'rows_scanned' => $rowsScanned,            // Total rows read from sheet
                'rows_collected' => $employeeCollected,     // Valid pending rows found
                'errors' => $employeeErrors ?: null,        // Errors (null if none)
                'status' => 'completed',                    // Mark as completed
                'completed_at' => now(),                    // End timestamp
            ]);

            // Add to the overall scan logs
            $scanLogs[] = $scanLog;
        }

        // Log the overall scan results
        $this->generic->log('info', 'Billing scan completed', [
            'employees_scanned' => count($employees),
            'total_rows_scanned' => $totalScanned,
            'total_rows_collected' => $totalCollected,
            'clients_with_items' => count($groupedByClient),
            'total_errors' => count($allErrors),
        ]);

        // Return the complete scan results for review
        return [
            'grouped_by_client' => $groupedByClient,     // Rows organized by client
            'scan_logs' => $scanLogs,                     // Per-employee scan logs
            'errors' => $allErrors,                       // All errors across employees
            'total_rows_scanned' => $totalScanned,        // Grand total rows read
            'total_rows_collected' => $totalCollected,     // Grand total valid rows
        ];
    }

    /**
     * Create invoices for scanned billable items.
     * Takes the grouped scan results and creates a Stripe draft invoice per client.
     *
     * @param array $groupedByClient Client-grouped rows from runScan()
     * @return array{invoices: array, errors: array} Created invoices and any errors
     */
    public function createInvoices(array $groupedByClient): array
    {
        // Initialize result containers
        $createdInvoices = []; // Successfully created invoices
        $errors = [];          // Any errors during creation

        // Process each client's billable items
        foreach ($groupedByClient as $clientName => $rows) {
            // Look up the client record by name
            $client = Client::where('name', $clientName)->active()->first();

            // If client not found, skip (shouldn't happen after scan validation, but be safe)
            if (!$client) {
                // Record the error
                $errors[] = [
                    'client' => $clientName,
                    'message' => 'Client not found in database',
                ];
                // Skip to next client
                continue;
            }

            // Calculate totals for this client
            $totalMinutes = 0;
            // Sum up all the minutes across this client's rows
            foreach ($rows as $row) {
                $totalMinutes += $row['time_minutes'];
            }

            // Calculate the dollar amount: (minutes / 60) × hourly rate
            $totalHours = $totalMinutes / 60;
            $totalAmount = round($totalHours * $client->hourly_rate, 2);

            // Convert to cents for Stripe (Stripe uses smallest currency unit)
            $amountCents = (int) round($totalAmount * 100);

            // Build the Stripe invoice description
            $description = config('hws.company_name') . ' — '
                . number_format($totalHours, 2) . ' hours @ '
                . config('hws.currency_symbol') . number_format($client->hourly_rate, 2) . '/hr';

            // ── Create the Stripe draft invoice ──
            $stripeResult = $this->stripeService->createDraftInvoice(
                $client->stripe_customer_id,  // Stripe Customer ID
                $description,                 // Line item description
                $amountCents                  // Amount in cents
            );

            // Check if Stripe invoice creation succeeded
            if (!$stripeResult['success']) {
                // Record the error
                $errors[] = [
                    'client' => $clientName,
                    'message' => 'Stripe error: ' . ($stripeResult['error'] ?? 'Unknown'),
                ];
                // Skip to next client — don't create local record without Stripe invoice
                continue;
            }

            // ── Build employee ranges for traceability ──
            $employeeRanges = [];
            foreach ($rows as $row) {
                // Get the employee ID
                $empId = (string) $row['employee_id'];
                // Initialize range for this employee if not set
                if (!isset($employeeRanges[$empId])) {
                    $employeeRanges[$empId] = [
                        'start' => $row['primary_key'],  // First primary_key for this employee
                        'end' => $row['primary_key'],    // Will be updated as we go
                    ];
                }
                // Update the end value to the current row's primary_key
                $employeeRanges[$empId]['end'] = max($employeeRanges[$empId]['end'], $row['primary_key']);
                // Update the start value to the minimum
                $employeeRanges[$empId]['start'] = min($employeeRanges[$empId]['start'], $row['primary_key']);
            }

            // ── Create the local invoice record ──
            $invoice = Invoice::create([
                'stripe_invoice_id' => $stripeResult['data']['id'],         // Stripe Invoice ID
                'client_id' => $client->id,                                  // Local client FK
                'total_minutes' => $totalMinutes,                           // Sum of all minutes
                'total_amount' => $totalAmount,                             // Dollar amount
                'status' => config('hws.invoice_statuses.draft'),           // Start as draft
                'employee_ranges' => $employeeRanges,                       // Per-employee PK ranges
                'stripe_invoice_url' => $stripeResult['data']['hosted_url'] ?? null, // Stripe URL
            ]);

            // ── Create line item records for each row ──
            foreach ($rows as $row) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,              // FK to invoice
                    'employee_id' => $row['employee_id'],      // FK to employee
                    'primary_key' => $row['primary_key'],      // Sheet primary_key
                    'date' => $row['date'],                    // Work date
                    'time_minutes' => $row['time_minutes'],    // Duration in minutes
                    'description' => $row['description'],      // Work description
                    'client_name' => $row['client_name'],      // Client name snapshot
                    'domain' => $row['domain'],                // Domain (stored, not processed)
                    'sheet_row_number' => $row['sheet_row_number'], // Sheet row for write-back
                ]);
            }

            // ── Deduct credits for hourly_credits clients ──
            if ($client->billing_type === 'hourly_credits') {
                // Deduct the billed hours from the credit balance
                $client->credit_balance_hours -= $totalHours;
                // Check if balance is now below the threshold
                if ($client->isCreditLow() && !$client->credit_alert_sent) {
                    // Flag the account for low credit alert
                    // The actual email is sent manually via UI button
                    $client->credit_alert_sent = true;
                }
                // Save the updated credit balance
                $client->save();
            }

            // Add to the created invoices list
            $createdInvoices[] = $invoice;
        }

        // Log the invoice creation results
        $this->generic->log('info', 'Invoices created', [
            'invoices_created' => count($createdInvoices),
            'errors' => count($errors),
        ]);

        // Return the results
        return [
            'invoices' => $createdInvoices,  // Successfully created invoices
            'errors' => $errors,              // Any errors encountered
        ];
    }

    /**
     * Mark an invoice as billed — updates employee Google Sheets and cursors.
     * Sets billed_status to "billed" on each source row and highlights with light red.
     * Updates each employee's scan_start_primary_key to the end of the invoice range.
     *
     * @param Invoice $invoice The invoice to mark as billed
     * @return array{success: bool, message: string, details?: array} Result
     */
    public function markAsBilled(Invoice $invoice): array
    {
        // Get all line items for this invoice, grouped by employee
        $lineItems = $invoice->lineItems()->get()->groupBy('employee_id');

        // Track results for each employee
        $details = [];

        // Get the billed status value from config
        $billedStatus = config('hws.billed_status.billed');

        // Process each employee's line items
        foreach ($lineItems as $employeeId => $items) {
            // Look up the employee record
            $employee = Employee::find($employeeId);

            // Skip if employee no longer exists
            if (!$employee) {
                // Record the skipped employee
                $details[] = [
                    'employee_id' => $employeeId,
                    'status' => 'skipped',
                    'reason' => 'Employee not found',
                ];
                // Continue to next employee
                continue;
            }

            // Build the row updates for this employee's sheet
            $rowUpdates = [];
            $rowNumbers = [];
            $maxPrimaryKey = 0;

            foreach ($items as $item) {
                // Only update rows that have a sheet_row_number
                if ($item->sheet_row_number) {
                    // Add this row to the update batch
                    $rowUpdates[] = [
                        'row_number' => $item->sheet_row_number,  // Which row to update
                        'status' => $billedStatus,                // New status value
                    ];
                    // Track the row number for highlighting
                    $rowNumbers[] = $item->sheet_row_number;
                }
                // Track the highest primary_key for cursor update
                $maxPrimaryKey = max($maxPrimaryKey, $item->primary_key);
            }

            // ── Update billed_status in the Google Sheet ──
            if (!empty($rowUpdates)) {
                $updateResult = $this->sheetsService->updateBilledStatus(
                    $employee->google_sheet_id,  // Sheet ID
                    $rowUpdates                  // Row updates
                );
            }

            // ── Apply light red highlighting to billed rows ──
            if (!empty($rowNumbers)) {
                $highlightResult = $this->sheetsService->highlightBilledRows(
                    $employee->google_sheet_id,  // Sheet ID
                    $rowNumbers                  // Rows to highlight
                );
            }

            // ── Update the employee's scan cursor ──
            // Store the previous value for rollback capability
            $employee->last_billing_primary_key = $employee->scan_start_primary_key;
            // Set the new starting point to the highest primary_key in this invoice
            $employee->scan_start_primary_key = $maxPrimaryKey;
            // Save the cursor updates
            $employee->save();

            // Record the result for this employee
            $details[] = [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
                'rows_updated' => count($rowUpdates),
                'new_cursor' => $maxPrimaryKey,
                'status' => 'success',
            ];
        }

        // Log the billing operation
        $this->generic->log('info', 'Invoice marked as billed', [
            'invoice_id' => $invoice->id,
            'employees_processed' => count($details),
        ]);

        // Return the complete result
        return [
            'success' => true,
            'message' => 'Invoice marked as billed successfully.',
            'details' => $details,
        ];
    }

    /**
     * Reverse billing for an invoice — resets employee sheet rows and cursors.
     * Used when corrections are needed after an invoice was marked as billed.
     *
     * @param Invoice $invoice     The invoice to reverse
     * @param string  $resetStatus The status to set rows back to (default: 'pending')
     * @return array{success: bool, message: string} Result
     */
    public function reverseBilling(Invoice $invoice, string $resetStatus = 'pending'): array
    {
        // Get all line items for this invoice, grouped by employee
        $lineItems = $invoice->lineItems()->get()->groupBy('employee_id');

        // Process each employee's line items
        foreach ($lineItems as $employeeId => $items) {
            // Look up the employee record
            $employee = Employee::find($employeeId);

            // Skip if employee no longer exists
            if (!$employee) {
                continue;
            }

            // Build the row updates to reset billed_status
            $rowUpdates = [];
            foreach ($items as $item) {
                if ($item->sheet_row_number) {
                    $rowUpdates[] = [
                        'row_number' => $item->sheet_row_number,  // Which row to update
                        'status' => $resetStatus,                 // Reset status (usually 'pending')
                    ];
                }
            }

            // ── Reset billed_status in the Google Sheet ──
            if (!empty($rowUpdates)) {
                $this->sheetsService->updateBilledStatus(
                    $employee->google_sheet_id,  // Sheet ID
                    $rowUpdates                  // Row updates
                );
            }

            // ── Roll back the employee's scan cursor ──
            // Restore the previous starting point
            $employee->scan_start_primary_key = $employee->last_billing_primary_key;
            // Save the cursor rollback
            $employee->save();
        }

        // ── Reverse credit deduction for hourly_credits clients ──
        $client = $invoice->client;
        if ($client && $client->billing_type === 'hourly_credits') {
            // Add the hours back to the credit balance
            $totalHours = $invoice->total_minutes / 60;
            $client->credit_balance_hours += $totalHours;
            // Reset the credit alert flag since balance is restored
            $client->credit_alert_sent = false;
            // Save the restored balance
            $client->save();
        }

        // Log the reversal
        $this->generic->log('info', 'Invoice billing reversed', [
            'invoice_id' => $invoice->id,
            'reset_status' => $resetStatus,
        ]);

        // Return success
        return [
            'success' => true,
            'message' => 'Billing reversed. Employee sheet rows reset to "' . $resetStatus . '".',
        ];
    }

    /**
     * Refresh payment status for an invoice by polling Stripe.
     * Updates the local invoice record with current Stripe status and payment details.
     *
     * @param Invoice $invoice The invoice to refresh
     * @return array{success: bool, message: string, status?: string} Result
     */
    public function refreshPaymentStatus(Invoice $invoice): array
    {
        // Skip if no Stripe invoice ID (shouldn't happen, but be safe)
        if (empty($invoice->stripe_invoice_id)) {
            return [
                'success' => false,
                'message' => 'No Stripe invoice ID associated with this invoice.',
            ];
        }

        // Poll Stripe for current invoice status
        $stripeResult = $this->stripeService->getInvoice($invoice->stripe_invoice_id);

        // Check if the Stripe call succeeded
        if (!$stripeResult['success']) {
            return [
                'success' => false,
                'message' => 'Stripe error: ' . ($stripeResult['error'] ?? 'Unknown'),
            ];
        }

        // Get the Stripe data
        $stripeData = $stripeResult['data'];

        // Map Stripe status to our local status
        $newStatus = $this->mapStripeStatus($stripeData['status'], $stripeData['paid'] ?? false);

        // Build update data
        $updateData = [
            'status' => $newStatus,                                   // Updated status
            'stripe_invoice_url' => $stripeData['hosted_url'] ?? $invoice->stripe_invoice_url, // Update URL if available
        ];

        // If paid, store the payment details
        if ($stripeData['paid'] && isset($stripeData['payment_details'])) {
            $updateData['stripe_payment_details'] = $stripeData['payment_details'];
        }

        // Update the local invoice record
        $invoice->update($updateData);

        // Log the status refresh
        $this->generic->log('info', 'Invoice payment status refreshed', [
            'invoice_id' => $invoice->id,
            'old_status' => $invoice->getOriginal('status'),
            'new_status' => $newStatus,
        ]);

        // Return the result
        return [
            'success' => true,
            'message' => 'Payment status updated to: ' . $newStatus,
            'status' => $newStatus,
        ];
    }

    /**
     * Map a Stripe invoice status to our local invoice status.
     * Stripe uses: draft, open, paid, uncollectible, void
     * We use: draft, sent, paid, void (from config)
     *
     * @param string $stripeStatus The status from Stripe
     * @param bool   $isPaid       Whether the invoice is fully paid
     * @return string Our local status value
     */
    protected function mapStripeStatus(string $stripeStatus, bool $isPaid): string
    {
        // If paid flag is true, it's paid regardless of status string
        if ($isPaid) {
            return config('hws.invoice_statuses.paid');
        }

        // Map Stripe statuses to our statuses
        return match ($stripeStatus) {
            'draft'         => config('hws.invoice_statuses.draft'),  // Still a draft
            'open'          => config('hws.invoice_statuses.sent'),   // Finalized/sent, awaiting payment
            'paid'          => config('hws.invoice_statuses.paid'),   // Paid
            'void'          => config('hws.invoice_statuses.void'),   // Voided
            'uncollectible' => config('hws.invoice_statuses.void'),   // Treat as void
            default         => config('hws.invoice_statuses.draft'),  // Unknown → default to draft
        };
    }
}

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
 *
 * CRITICAL RULE: NEVER silently skip any row marked as hourly/pending.
 * Every pending row either gets collected or generates a visible error.
 * Skipping billable items causes the company to lose money.
 */
class BillingService
{
    protected GoogleSheetsService $sheetsService;
    protected StripeService $stripeService;
    protected GenericService $generic;

    public function __construct(
        GoogleSheetsService $sheetsService,
        StripeService $stripeService,
        GenericService $generic
    ) {
        $this->sheetsService = $sheetsService;
        $this->stripeService = $stripeService;
        $this->generic = $generic;
    }

    /**
     * Run the billing scan across all active employees.
     * Reads new rows from each employee's Google Sheet, validates them,
     * and groups them by client for review before invoice creation.
     *
     * ENFORCES: Every pending row is either collected or produces a critical error.
     *
     * @return array Scan results organized for display
     */
    public function runScan(): array
    {
        $employees = Employee::active()->get();

        $groupedByClient = [];
        $scanLogs = [];
        $allErrors = [];
        $totalScanned = 0;
        $totalCollected = 0;
        $skippedBillableMinutes = 0; // Track lost revenue from skipped rows
        $skippedBillableRows = 0;    // Count of rows that couldn't be processed

        // Config values
        $colPrimaryKey = config('hws.sheet_columns.primary_key');
        $colBilledStatus = config('hws.sheet_columns.billed_status');
        $colClient = config('hws.sheet_columns.client');
        $colTime = config('hws.sheet_columns.time');
        $colDate = config('hws.sheet_columns.date');
        $colDescription = config('hws.sheet_columns.description');
        $colDomain = config('hws.sheet_columns.domain');
        $pendingStatus = config('hws.billed_status.pending');

        // Valid client names for matching
        $validClients = Client::active()->pluck('name')->toArray();

        foreach ($employees as $employee) {
            // Skip employees with no sheet configured
            if (empty($employee->google_sheet_id)) {
                $allErrors[] = [
                    'type'     => 'no_sheet',
                    'severity' => 'warning',
                    'employee' => $employee->name,
                    'message'  => 'Employee "' . $employee->name . '" has no Google Sheet configured. Skipped.',
                ];
                continue;
            }

            $scanLog = ScanLog::create([
                'employee_id' => $employee->id,
                'scan_type'   => 'billing',
                'started_at'  => now(),
                'status'      => 'running',
            ]);

            $employeeErrors = [];
            $employeeCollected = 0;

            // Read rows from the employee's sheet
            $result = $this->sheetsService->readRows(
                $employee->google_sheet_id,
                $employee->scan_start_primary_key
            );

            if (!$result['success']) {
                $employeeErrors[] = [
                    'type'     => 'sheet_read_error',
                    'severity' => 'critical',
                    'message'  => $result['error'] ?? 'Unknown error reading sheet',
                ];

                $scanLog->update([
                    'status'       => 'failed',
                    'errors'       => $employeeErrors,
                    'completed_at' => now(),
                ]);
                $scanLogs[] = $scanLog;
                continue;
            }

            $rows = $result['rows'];
            $rowsScanned = count($rows);
            $totalScanned += $rowsScanned;

            foreach ($rows as $row) {
                // Only process pending (billable) rows
                $billedStatus = $row[$colBilledStatus] ?? '';
                if (strtolower(trim($billedStatus)) !== $pendingStatus) {
                    continue; // Non-pending rows are fine to skip
                }

                // ══════════════════════════════════════════════════
                // BELOW THIS POINT: Row is PENDING (hourly/billable).
                // NEVER silently skip. Every failure = visible error.
                // ══════════════════════════════════════════════════

                $clientName = trim($row[$colClient] ?? '');
                $timeValue = $row[$colTime] ?? '';
                $rowRef = 'Row ' . ($row['_sheet_row_number'] ?? '?') . ' (PK: ' . ($row[$colPrimaryKey] ?? '?') . ')';

                // ── Check: Client name present ──
                if (empty($clientName)) {
                    $minutes = is_numeric($timeValue) ? (int) $timeValue : 0;
                    $skippedBillableMinutes += $minutes;
                    $skippedBillableRows++;
                    $employeeErrors[] = [
                        'type'     => 'missing_client',
                        'severity' => 'critical',
                        'row'      => $row['_sheet_row_number'] ?? 'unknown',
                        'pk'       => $row[$colPrimaryKey] ?? 'unknown',
                        'minutes'  => $minutes,
                        'message'  => $rowRef . ': PENDING row has NO CLIENT. ' . $minutes . ' minutes of billable work cannot be invoiced.',
                    ];
                    continue;
                }

                // ── Check: Client exists in system ──
                if (!in_array($clientName, $validClients)) {
                    $minutes = is_numeric($timeValue) ? (int) $timeValue : 0;
                    $skippedBillableMinutes += $minutes;
                    $skippedBillableRows++;
                    $employeeErrors[] = [
                        'type'     => 'unmatched_client',
                        'severity' => 'critical',
                        'row'      => $row['_sheet_row_number'] ?? 'unknown',
                        'pk'       => $row[$colPrimaryKey] ?? 'unknown',
                        'client'   => $clientName,
                        'minutes'  => $minutes,
                        'message'  => $rowRef . ': Client "' . $clientName . '" not found in system. ' . $minutes . ' minutes of billable work cannot be invoiced.',
                    ];
                    continue;
                }

                // ── Check: Time is valid ──
                if (!is_numeric($timeValue) || (int) $timeValue <= 0) {
                    $skippedBillableRows++;
                    $employeeErrors[] = [
                        'type'     => 'invalid_time',
                        'severity' => 'critical',
                        'row'      => $row['_sheet_row_number'] ?? 'unknown',
                        'pk'       => $row[$colPrimaryKey] ?? 'unknown',
                        'value'    => $timeValue,
                        'message'  => $rowRef . ': PENDING row has invalid time value "' . $timeValue . '". Cannot calculate billable amount.',
                    ];
                    continue;
                }

                // ── Check: Date is present ──
                $dateValue = trim($row[$colDate] ?? '');
                if (empty($dateValue)) {
                    $minutes = (int) $timeValue;
                    $skippedBillableMinutes += $minutes;
                    $skippedBillableRows++;
                    $employeeErrors[] = [
                        'type'     => 'missing_date',
                        'severity' => 'critical',
                        'row'      => $row['_sheet_row_number'] ?? 'unknown',
                        'pk'       => $row[$colPrimaryKey] ?? 'unknown',
                        'minutes'  => $minutes,
                        'message'  => $rowRef . ': PENDING row has no date. ' . $minutes . ' minutes of billable work cannot be invoiced.',
                    ];
                    continue;
                }

                // ── Row passed all validation — collect it ──
                if (!isset($groupedByClient[$clientName])) {
                    $groupedByClient[$clientName] = [];
                }

                $groupedByClient[$clientName][] = [
                    'employee_id'      => $employee->id,
                    'employee_name'    => $employee->name,
                    'primary_key'      => (int) $row[$colPrimaryKey],
                    'date'             => $dateValue,
                    'time_minutes'     => (int) $timeValue,
                    'description'      => $row[$colDescription] ?? '',
                    'client_name'      => $clientName,
                    'domain'           => $row[$colDomain] ?? '',
                    'sheet_row_number' => $row['_sheet_row_number'] ?? null,
                ];

                $employeeCollected++;
            }

            $totalCollected += $employeeCollected;
            $allErrors = array_merge($allErrors, $employeeErrors);

            $scanLog->update([
                'rows_scanned'   => $rowsScanned,
                'rows_collected' => $employeeCollected,
                'errors'         => $employeeErrors ?: null,
                'status'         => 'completed',
                'completed_at'   => now(),
            ]);
            $scanLogs[] = $scanLog;
        }

        $this->generic->log('info', 'Billing scan completed', [
            'employees_scanned'       => count($employees),
            'total_rows_scanned'      => $totalScanned,
            'total_rows_collected'    => $totalCollected,
            'clients_with_items'      => count($groupedByClient),
            'total_errors'            => count($allErrors),
            'skipped_billable_rows'   => $skippedBillableRows,
            'skipped_billable_minutes' => $skippedBillableMinutes,
        ]);

        return [
            'grouped_by_client'        => $groupedByClient,
            'scan_logs'                => $scanLogs,
            'errors'                   => $allErrors,
            'total_rows_scanned'       => $totalScanned,
            'total_rows_collected'     => $totalCollected,
            'skipped_billable_rows'    => $skippedBillableRows,
            'skipped_billable_minutes' => $skippedBillableMinutes,
        ];
    }

    /**
     * Create invoices for scanned billable items.
     * Uses the client's hourly billing Stripe link to determine the correct account.
     *
     * Flags (but does not skip) clients without Stripe links.
     *
     * @param array $groupedByClient Client-grouped rows from runScan()
     * @return array{invoices: array, errors: array, warnings: array}
     */
    public function createInvoices(array $groupedByClient): array
    {
        $createdInvoices = [];
        $errors = [];
        $warnings = [];

        foreach ($groupedByClient as $clientName => $rows) {
            $client = Client::where('name', $clientName)->active()->first();

            if (!$client) {
                $errors[] = [
                    'client'  => $clientName,
                    'message' => 'Client not found in database.',
                ];
                continue;
            }

            // ── Resolve Stripe account via hourly billing link ──
            $hourlyLink = $client->hourlyBillingLink();
            $stripeCustomerId = null;
            $stripeAccountId = null;

            if ($hourlyLink) {
                $stripeCustomerId = $hourlyLink->stripe_customer_id;
                $stripeAccountId = $hourlyLink->stripe_account_id;
            } elseif ($client->stripe_customer_id) {
                // Fallback: legacy stripe_customer_id field
                $stripeCustomerId = $client->stripe_customer_id;
                $warnings[] = [
                    'client'  => $clientName,
                    'message' => 'Using legacy stripe_customer_id. Set an hourly billing Stripe profile for this client.',
                ];
            }

            if (!$stripeCustomerId) {
                $totalMinutes = array_sum(array_column($rows, 'time_minutes'));
                $errors[] = [
                    'client'  => $clientName,
                    'message' => 'No Stripe customer ID linked. Cannot create invoice for '
                        . $totalMinutes . ' minutes of billable work. Add a Stripe profile and mark it as hourly billing.',
                ];
                continue;
            }

            // Calculate totals
            $totalMinutes = 0;
            foreach ($rows as $row) {
                $totalMinutes += $row['time_minutes'];
            }
            $totalHours = $totalMinutes / 60;
            $totalAmount = round($totalHours * $client->hourly_rate, 2);
            $amountCents = (int) round($totalAmount * 100);

            $description = config('hws.company_name') . ' — '
                . number_format($totalHours, 2) . ' hours @ '
                . config('hws.currency_symbol') . number_format($client->hourly_rate, 2) . '/hr';

            // ── Create Stripe draft invoice on the correct account ──
            $stripeResult = $this->stripeService->createDraftInvoice(
                $stripeCustomerId,
                $description,
                $amountCents,
                $stripeAccountId
            );

            if (!$stripeResult['success']) {
                $errors[] = [
                    'client'  => $clientName,
                    'message' => 'Stripe error: ' . ($stripeResult['error'] ?? 'Unknown'),
                ];
                continue;
            }

            // ── Build employee ranges ──
            $employeeRanges = [];
            foreach ($rows as $row) {
                $empId = (string) $row['employee_id'];
                if (!isset($employeeRanges[$empId])) {
                    $employeeRanges[$empId] = [
                        'start' => $row['primary_key'],
                        'end'   => $row['primary_key'],
                    ];
                }
                $employeeRanges[$empId]['end'] = max($employeeRanges[$empId]['end'], $row['primary_key']);
                $employeeRanges[$empId]['start'] = min($employeeRanges[$empId]['start'], $row['primary_key']);
            }

            // ── Create local invoice record with Stripe account reference ──
            $invoice = Invoice::create([
                'stripe_invoice_id'  => $stripeResult['data']['id'],
                'client_id'          => $client->id,
                'stripe_account_id'  => $stripeAccountId,
                'total_minutes'      => $totalMinutes,
                'total_amount'       => $totalAmount,
                'status'             => config('hws.invoice_statuses.draft'),
                'employee_ranges'    => $employeeRanges,
                'stripe_invoice_url' => $stripeResult['data']['hosted_url'] ?? null,
            ]);

            // ── Create line item records ──
            foreach ($rows as $row) {
                InvoiceLineItem::create([
                    'invoice_id'       => $invoice->id,
                    'employee_id'      => $row['employee_id'],
                    'primary_key'      => $row['primary_key'],
                    'date'             => $row['date'],
                    'time_minutes'     => $row['time_minutes'],
                    'description'      => $row['description'],
                    'client_name'      => $row['client_name'],
                    'domain'           => $row['domain'],
                    'sheet_row_number' => $row['sheet_row_number'],
                ]);
            }

            // ── Deduct credits for hourly_credits clients ──
            if ($client->billing_type === 'hourly_credits') {
                $client->credit_balance_hours -= $totalHours;
                if ($client->isCreditLow() && !$client->credit_alert_sent) {
                    $client->credit_alert_sent = true;
                }
                $client->save();
            }

            $createdInvoices[] = $invoice;
        }

        $this->generic->log('info', 'Invoices created', [
            'invoices_created' => count($createdInvoices),
            'errors'           => count($errors),
            'warnings'         => count($warnings),
        ]);

        return [
            'invoices' => $createdInvoices,
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Mark an invoice as billed — updates employee Google Sheets and cursors.
     * Tracks previous cursor value before updating.
     *
     * @param Invoice $invoice The invoice to mark as billed
     * @return array{success: bool, message: string, details?: array}
     */
    public function markAsBilled(Invoice $invoice): array
    {
        $lineItems = $invoice->lineItems()->get()->groupBy('employee_id');
        $details = [];
        $billedStatus = config('hws.billed_status.billed');

        foreach ($lineItems as $employeeId => $items) {
            $employee = Employee::find($employeeId);

            if (!$employee) {
                $details[] = [
                    'employee_id' => $employeeId,
                    'status'      => 'skipped',
                    'reason'      => 'Employee not found',
                ];
                continue;
            }

            $rowUpdates = [];
            $rowNumbers = [];
            $maxPrimaryKey = 0;

            foreach ($items as $item) {
                if ($item->sheet_row_number) {
                    $rowUpdates[] = [
                        'row_number' => $item->sheet_row_number,
                        'status'     => $billedStatus,
                    ];
                    $rowNumbers[] = $item->sheet_row_number;
                }
                $maxPrimaryKey = max($maxPrimaryKey, $item->primary_key);
            }

            if (!empty($rowUpdates)) {
                $this->sheetsService->updateBilledStatus(
                    $employee->google_sheet_id,
                    $rowUpdates
                );
            }

            if (!empty($rowNumbers)) {
                $this->sheetsService->highlightBilledRows(
                    $employee->google_sheet_id,
                    $rowNumbers
                );
            }

            // Track previous cursor before updating
            $employee->previous_scan_start_primary_key = $employee->scan_start_primary_key;
            $employee->last_billing_primary_key = $employee->scan_start_primary_key;
            $employee->scan_start_primary_key = $maxPrimaryKey;
            $employee->save();

            $details[] = [
                'employee_id'   => $employeeId,
                'employee_name' => $employee->name,
                'rows_updated'  => count($rowUpdates),
                'new_cursor'    => $maxPrimaryKey,
                'status'        => 'success',
            ];
        }

        $this->generic->log('info', 'Invoice marked as billed', [
            'invoice_id'          => $invoice->id,
            'employees_processed' => count($details),
        ]);

        return [
            'success' => true,
            'message' => 'Invoice marked as billed successfully.',
            'details' => $details,
        ];
    }

    /**
     * Reverse billing for an invoice — resets employee sheet rows and cursors.
     *
     * @param Invoice $invoice     The invoice to reverse
     * @param string  $resetStatus The status to set rows back to (default: 'pending')
     * @return array{success: bool, message: string}
     */
    public function reverseBilling(Invoice $invoice, string $resetStatus = 'pending'): array
    {
        $lineItems = $invoice->lineItems()->get()->groupBy('employee_id');

        foreach ($lineItems as $employeeId => $items) {
            $employee = Employee::find($employeeId);
            if (!$employee) continue;

            $rowUpdates = [];
            foreach ($items as $item) {
                if ($item->sheet_row_number) {
                    $rowUpdates[] = [
                        'row_number' => $item->sheet_row_number,
                        'status'     => $resetStatus,
                    ];
                }
            }

            if (!empty($rowUpdates)) {
                $this->sheetsService->updateBilledStatus(
                    $employee->google_sheet_id,
                    $rowUpdates
                );
            }

            // Roll back cursor
            $employee->scan_start_primary_key = $employee->previous_scan_start_primary_key;
            $employee->save();
        }

        // Reverse credit deduction for hourly_credits clients
        $client = $invoice->client;
        if ($client && $client->billing_type === 'hourly_credits') {
            $totalHours = $invoice->total_minutes / 60;
            $client->credit_balance_hours += $totalHours;
            $client->credit_alert_sent = false;
            $client->save();
        }

        $this->generic->log('info', 'Invoice billing reversed', [
            'invoice_id'   => $invoice->id,
            'reset_status' => $resetStatus,
        ]);

        return [
            'success' => true,
            'message' => 'Billing reversed. Employee sheet rows reset to "' . $resetStatus . '".',
        ];
    }

    /**
     * Refresh payment status for an invoice by polling Stripe.
     * Uses the invoice's stripe_account_id to query the correct Stripe account.
     *
     * @param Invoice $invoice The invoice to refresh
     * @return array{success: bool, message: string, status?: string}
     */
    public function refreshPaymentStatus(Invoice $invoice): array
    {
        if (empty($invoice->stripe_invoice_id)) {
            return ['success' => false, 'message' => 'No Stripe invoice ID associated.'];
        }

        // Use the invoice's Stripe account for the API call
        $stripeResult = $this->stripeService->getInvoice(
            $invoice->stripe_invoice_id,
            $invoice->stripe_account_id
        );

        if (!$stripeResult['success']) {
            return ['success' => false, 'message' => 'Stripe error: ' . ($stripeResult['error'] ?? 'Unknown')];
        }

        $stripeData = $stripeResult['data'];
        $newStatus = $this->mapStripeStatus($stripeData['status'], $stripeData['paid'] ?? false);

        $updateData = [
            'status'             => $newStatus,
            'stripe_invoice_url' => $stripeData['hosted_url'] ?? $invoice->stripe_invoice_url,
        ];

        if ($stripeData['paid'] && isset($stripeData['payment_details'])) {
            $updateData['stripe_payment_details'] = $stripeData['payment_details'];
        }

        $invoice->update($updateData);

        $this->generic->log('info', 'Invoice payment status refreshed', [
            'invoice_id' => $invoice->id,
            'new_status' => $newStatus,
        ]);

        return ['success' => true, 'message' => 'Status updated.', 'status' => $newStatus];
    }

    /**
     * Map Stripe invoice status to our internal status.
     *
     * @param string $stripeStatus Stripe invoice status
     * @param bool   $paid         Whether Stripe reports it as paid
     * @return string Our internal status value
     */
    protected function mapStripeStatus(string $stripeStatus, bool $paid): string
    {
        if ($paid) return config('hws.invoice_statuses.paid');

        return match ($stripeStatus) {
            'draft'         => config('hws.invoice_statuses.draft'),
            'open'          => config('hws.invoice_statuses.sent'),
            'paid'          => config('hws.invoice_statuses.paid'),
            'void'          => config('hws.invoice_statuses.void', 'void'),
            'uncollectible' => config('hws.invoice_statuses.void', 'void'),
            default         => config('hws.invoice_statuses.draft'),
        };
    }
}

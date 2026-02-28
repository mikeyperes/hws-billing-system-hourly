<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\RepeatCellRequest;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\Color;
use Google\Service\Sheets\GridRange;

/**
 * GoogleSheetsService — ALL Google Sheets API interactions go through this service.
 * Handles reading rows, writing billed_status, applying cell formatting,
 * and validating sheet access.
 *
 * NO other file should make Google Sheets API calls. This is enforced by spec.
 */
class GoogleSheetsService
{
    /**
     * @var GoogleSheets|null The Google Sheets API service instance
     */
    protected ?GoogleSheets $sheets = null;

    /**
     * @var GenericService Shared utility service for logging
     */
    protected GenericService $generic;

    /**
     * Constructor — inject the shared GenericService.
     *
     * @param GenericService $generic Shared utility service
     */
    public function __construct(GenericService $generic)
    {
        // Store reference to generic service for logging
        $this->generic = $generic;
    }

    /**
     * Get or create the Google Sheets API service instance.
     * Lazy-loaded — only initialized when first needed.
     * Authenticates using the service account JSON key file.
     *
     * @return GoogleSheets The authenticated Sheets service
     * @throws \RuntimeException If credentials file is missing or invalid
     */
    protected function getService(): GoogleSheets
    {
        // Return existing service if already initialized
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        // Get the credentials file path from config/hws.php
        $credentialsPath = config('hws.google.credentials_path');

        // Validate the credentials file exists
        if (!file_exists($credentialsPath)) {
            // Log the error before throwing
            $this->generic->log('error', 'Google credentials file not found', [
                'path' => $credentialsPath,
            ]);
            // Throw exception — can't proceed without credentials
            throw new \RuntimeException('Google credentials file not found at: ' . $credentialsPath);
        }

        // Create a new Google API client
        $client = new GoogleClient();
        // Set the application name for API request headers
        $client->setApplicationName(config('hws.app_name'));
        // Authenticate using the service account JSON key file
        $client->setAuthConfig($credentialsPath);
        // Request read/write access to Google Sheets
        $client->addScope(GoogleSheets::SPREADSHEETS);

        // Create and cache the Sheets service instance
        $this->sheets = new GoogleSheets($client);

        // Return the initialized service
        return $this->sheets;
    }

    /**
     * Validate that the service account has read/write access to a Google Sheet.
     * Called when adding or updating an employee's sheet ID.
     *
     * @param string $sheetId The Google Sheet ID to validate
     * @return array{success: bool, message: string, headers?: array} Validation result
     */
    public function validateSheetAccess(string $sheetId): array
    {
        try {
            // Attempt to read Row 1 (headers) from the sheet
            $response = $this->getService()->spreadsheets_values->get(
                $sheetId,  // The sheet ID to access
                'A1:Z1'    // Range: Row 1, columns A through Z
            );

            // Get the header row values
            $headers = $response->getValues();

            // Check if we got any data back
            if (empty($headers) || empty($headers[0])) {
                // Sheet is accessible but has no headers
                return [
                    'success' => false,
                    'message' => 'Sheet is accessible but Row 1 (headers) is empty. Please add column headers.',
                ];
            }

            // Get the expected column headers from config
            $expectedColumns = array_values(config('hws.sheet_columns'));

            // Check that all expected columns exist in the header row
            $actualHeaders = $headers[0];

            // Find any missing columns by comparing expected vs actual
            $missing = array_diff($expectedColumns, $actualHeaders);

            // If any expected columns are missing, report them
            if (!empty($missing)) {
                return [
                    'success' => false,
                    'message' => 'Missing required columns: ' . implode(', ', $missing),
                    'headers' => $actualHeaders, // Return what we found for debugging
                ];
            }

            // Log successful validation
            $this->generic->log('info', 'Sheet access validated', [
                'sheet_id' => $sheetId,
                'headers' => $actualHeaders,
            ]);

            // All checks passed
            return [
                'success' => true,
                'message' => 'Sheet is accessible and has all required columns.',
                'headers' => $actualHeaders, // Return headers for reference
            ];

        } catch (\Exception $e) {
            // Log the access failure
            $this->generic->log('error', 'Sheet access validation failed', [
                'sheet_id' => $sheetId,
                'error' => $e->getMessage(),
            ]);

            // Return failure with helpful instructions
            return [
                'success' => false,
                'message' => 'Cannot access sheet. Ensure it is shared with the service account email ('
                    . config('hws.google.service_account_email')
                    . ') as Editor. Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test if the service account has write access to a Google Sheet.
     * Reads cell A1 and writes the same value back (no actual change).
     *
     * @param string $sheetId The Google Sheet ID to test
     * @return array{success: bool, message: string}
     */
    public function testWriteAccess(string $sheetId): array
    {
        try {
            $service = $this->getService();

            // Read current value of A1
            $response = $service->spreadsheets_values->get($sheetId, 'A1');
            $currentValue = $response->getValues()[0][0] ?? '';

            // Write the same value back (tests write permission without changing data)
            $body = new \Google\Service\Sheets\ValueRange(['values' => [[$currentValue]]]);
            $service->spreadsheets_values->update($sheetId, 'A1', $body, [
                'valueInputOption' => 'RAW',
            ]);

            return ['success' => true, 'message' => 'Service account has write access to the sheet.'];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'not have permission') || str_contains($msg, 'PERMISSION_DENIED')) {
                return ['success' => false, 'message' => 'Sheet is READ-ONLY. Share with Editor permission for billing write-back.'];
            }
            return ['success' => false, 'message' => 'Write test failed: ' . $msg];
        }
    }

    /**
     * Read all rows from an employee's Google Sheet starting after a given primary key.
     * Returns rows as associative arrays using the column headers as keys.
     *
     * @param string $sheetId        The Google Sheet ID
     * @param int    $afterPrimaryKey Only return rows where primary_key > this value
     * @return array{success: bool, rows?: array, error?: string} Result with rows or error
     */
    public function readRows(string $sheetId, int $afterPrimaryKey = 0): array
    {
        try {
            // First, read the header row to get column positions
            $headerResponse = $this->getService()->spreadsheets_values->get(
                $sheetId,  // The sheet ID
                'A1:Z1'    // Row 1: headers
            );

            // Extract the header names from the response
            $headers = $headerResponse->getValues()[0] ?? [];

            // If no headers found, can't process the sheet
            if (empty($headers)) {
                return [
                    'success' => false,
                    'error' => 'No headers found in Row 1 of sheet ' . $sheetId,
                ];
            }

            // Find the column index for primary_key (needed for filtering)
            $pkColumn = config('hws.sheet_columns.primary_key');
            // Search the headers array for the primary_key column name
            $pkIndex = array_search($pkColumn, $headers);

            // Validate the primary_key column exists
            if ($pkIndex === false) {
                return [
                    'success' => false,
                    'error' => 'Column "' . $pkColumn . '" not found in sheet headers.',
                ];
            }

            // Read all data rows (starting from Row 2, skipping the header)
            $dataResponse = $this->getService()->spreadsheets_values->get(
                $sheetId,    // The sheet ID
                'A2:Z10000'  // Row 2 through 10000 — covers reasonable sheet sizes
            );

            // Get the raw row data
            $rawRows = $dataResponse->getValues() ?? [];

            // Build associative arrays from the raw data, filtering by primary_key
            $filteredRows = [];

            // Loop through each raw row
            foreach ($rawRows as $rowIndex => $row) {
                // Get the primary_key value for this row (may not exist if row is short)
                $pkValue = isset($row[$pkIndex]) ? (int) $row[$pkIndex] : 0;

                // Skip rows where primary_key <= the starting point
                if ($pkValue <= $afterPrimaryKey) {
                    continue;
                }

                // Build an associative array using headers as keys
                $assocRow = [];
                foreach ($headers as $colIndex => $headerName) {
                    // Use the header name as the key, and the cell value (or empty string) as the value
                    $assocRow[$headerName] = $row[$colIndex] ?? '';
                }

                // Add the actual Google Sheet row number (1-indexed, +2 for header and 0-index)
                $assocRow['_sheet_row_number'] = $rowIndex + 2;

                // Add to the filtered results
                $filteredRows[] = $assocRow;
            }

            // Log the read operation
            $this->generic->log('info', 'Sheet rows read', [
                'sheet_id' => $sheetId,
                'after_pk' => $afterPrimaryKey,
                'total_raw_rows' => count($rawRows),
                'filtered_rows' => count($filteredRows),
            ]);

            // Return the filtered, associative rows
            return [
                'success' => true,
                'rows' => $filteredRows,
            ];

        } catch (\Exception $e) {
            // Log the read failure
            $this->generic->log('error', 'Sheet read failed', [
                'sheet_id' => $sheetId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => 'Failed to read sheet: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update the billed_status column for specific rows in an employee's Google Sheet.
     * Used after marking an invoice as billed, or when reversing billing.
     *
     * @param string $sheetId   The Google Sheet ID
     * @param array  $rowUpdates Array of ['row_number' => int, 'status' => string]
     * @return array{success: bool, updated?: int, error?: string} Result with count or error
     */
    public function updateBilledStatus(string $sheetId, array $rowUpdates): array
    {
        try {
            // First, get the headers to find the billed_status column position
            $headerResponse = $this->getService()->spreadsheets_values->get(
                $sheetId,  // The sheet ID
                'A1:Z1'    // Row 1: headers
            );

            // Extract headers
            $headers = $headerResponse->getValues()[0] ?? [];

            // Find the billed_status column index
            $statusColumn = config('hws.sheet_columns.billed_status');
            // Search headers for the column name
            $statusIndex = array_search($statusColumn, $headers);

            // Validate the column exists
            if ($statusIndex === false) {
                return [
                    'success' => false,
                    'error' => 'Column "' . $statusColumn . '" not found in sheet headers.',
                ];
            }

            // Convert the column index to a letter (A=0, B=1, etc.)
            $columnLetter = chr(65 + $statusIndex);

            // Build batch update data — one ValueRange per row
            $updateData = [];
            foreach ($rowUpdates as $update) {
                // Build the cell reference (e.g., "D5" for column D, row 5)
                $range = $columnLetter . $update['row_number'];

                // Create a ValueRange for this cell update
                $valueRange = new ValueRange();
                // Set the range to the specific cell
                $valueRange->setRange($range);
                // Set the new value for this cell
                $valueRange->setValues([[$update['status']]]);

                // Add to the batch
                $updateData[] = $valueRange;
            }

            // Execute the batch update if there are any updates
            if (!empty($updateData)) {
                // Use batchUpdate for efficiency — one API call for all rows
                $body = new \Google\Service\Sheets\BatchUpdateValuesRequest();
                // Set the value input option to RAW (no formula parsing)
                $body->setValueInputOption('RAW');
                // Set all the cell updates
                $body->setData($updateData);

                // Execute the batch update API call
                $this->getService()->spreadsheets_values->batchUpdate($sheetId, $body);
            }

            // Log the update
            $this->generic->log('info', 'Sheet billed_status updated', [
                'sheet_id' => $sheetId,
                'rows_updated' => count($rowUpdates),
            ]);

            // Return success with count of updated rows
            return [
                'success' => true,
                'updated' => count($rowUpdates),
            ];

        } catch (\Exception $e) {
            // Log the update failure
            $this->generic->log('error', 'Sheet billed_status update failed', [
                'sheet_id' => $sheetId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => 'Failed to update sheet: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Apply light red background color to billed rows in an employee's Google Sheet.
     * Called after marking rows as billed to provide visual feedback.
     *
     * @param string $sheetId    The Google Sheet ID
     * @param array  $rowNumbers Array of 1-indexed row numbers to highlight
     * @return array{success: bool, error?: string} Result
     */
    public function highlightBilledRows(string $sheetId, array $rowNumbers): array
    {
        try {
            // Get the sheet's internal sheet ID (different from the spreadsheet ID)
            // The first sheet in a spreadsheet typically has sheetId = 0
            $spreadsheet = $this->getService()->spreadsheets->get($sheetId);
            // Get the first sheet's properties
            $internalSheetId = $spreadsheet->getSheets()[0]->getProperties()->getSheetId();

            // Build the requests array — one formatting request per row
            $requests = [];
            foreach ($rowNumbers as $rowNumber) {
                // Create a RepeatCell request to format the entire row
                $request = new SheetsRequest();

                // Build the cell format with the light red background color from config
                $colorConfig = config('hws.billed_cell_color');

                // Set up the request as a raw array (Google API accepts this format)
                $request->setRepeatCell([
                    // Define the range: entire row, all columns
                    'range' => [
                        'sheetId' => $internalSheetId,
                        'startRowIndex' => $rowNumber - 1,  // 0-indexed (row 5 = index 4)
                        'endRowIndex' => $rowNumber,        // Exclusive end
                        'startColumnIndex' => 0,            // Start at column A
                        'endColumnIndex' => 7,              // 7 columns (A through G)
                    ],
                    // Define the cell formatting
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => [
                                'red' => $colorConfig['red'],     // Red component (0.0-1.0)
                                'green' => $colorConfig['green'], // Green component
                                'blue' => $colorConfig['blue'],   // Blue component
                            ],
                        ],
                    ],
                    // Only update the background color, don't touch anything else
                    'fields' => 'userEnteredFormat.backgroundColor',
                ]);

                // Add this request to the batch
                $requests[] = $request;
            }

            // Execute the batch formatting request if there are any rows to highlight
            if (!empty($requests)) {
                // Create the batch update request
                $batchRequest = new BatchUpdateSpreadsheetRequest();
                // Set all formatting requests
                $batchRequest->setRequests($requests);

                // Execute the batch update API call
                $this->getService()->spreadsheets->batchUpdate($sheetId, $batchRequest);
            }

            // Log the formatting operation
            $this->generic->log('info', 'Sheet rows highlighted', [
                'sheet_id' => $sheetId,
                'rows_highlighted' => count($rowNumbers),
            ]);

            // Return success
            return ['success' => true];

        } catch (\Exception $e) {
            // Log the formatting failure
            $this->generic->log('error', 'Sheet row highlighting failed', [
                'sheet_id' => $sheetId,
                'error' => $e->getMessage(),
            ]);

            // Return failure response
            return [
                'success' => false,
                'error' => 'Failed to highlight rows: ' . $e->getMessage(),
            ];
        }
    }
}

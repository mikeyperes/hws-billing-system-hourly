<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * GenericService — shared utility functions used across the entire system.
 * All cross-cutting helper methods live here to prevent code duplication.
 * Every other service and controller should use these methods instead of
 * implementing their own versions.
 */
class GenericService
{
    /**
     * Run a shell command and return trimmed output.
     * Centralizes the trim(shell_exec()) pattern used across the system.
     *
     * @param string $command Shell command to execute
     * @return string Trimmed output (empty string on failure)
     */
    public function runCommand(string $command): string
    {
        return trim(shell_exec($command . ' 2>&1') ?? '');
    }

    /**
     * Get invoice summary stats (counts and amounts by status).
     * Used by Dashboard and any future module that needs invoice summaries.
     *
     * @return array{counts: array, amounts: array} Invoice stats grouped by status
     */
    public function getInvoiceStats(): array
    {
        $statuses = [
            'draft' => config('hws.invoice_statuses.draft'),
            'sent'  => config('hws.invoice_statuses.sent'),
            'paid'  => config('hws.invoice_statuses.paid'),
        ];

        $counts = [];
        $amounts = [];

        foreach ($statuses as $key => $status) {
            $query = \App\Models\Invoice::where('status', $status);
            $counts[$key] = (clone $query)->count();
            $amounts[$key] = (clone $query)->sum('total_amount');
        }

        return ['counts' => $counts, 'amounts' => $amounts];
    }

    /**
     * Get cloud services summary stats.
     * Used by Dashboard and HostingController overview.
     *
     * @return array Cloud stats (servers, accounts, subscriptions, revenue)
     */
    public function getCloudStats(): array
    {
        return [
            'servers'              => \App\Models\WhmServer::count(),
            'total_accounts'       => \App\Models\HostingAccount::count(),
            'active_accounts'      => \App\Models\HostingAccount::where('status', 'active')->count(),
            'active_subscriptions' => \App\Models\HostingSubscription::where('status', 'active')->count(),
            'monthly_revenue'      => \App\Models\HostingSubscription::where('status', 'active')
                ->where('interval', 'month')
                ->sum('amount_cents'),
        ];
    }
    /**
     * Format a number of minutes into a human-readable hours string.
     * Example: 150 minutes → "2.50 hrs"
     *
     * @param int $minutes Total minutes to convert
     * @return string Formatted hours string (e.g., "2.50 hrs")
     */
    public function formatMinutesToHours(int $minutes): string
    {
        // Divide minutes by 60 and round to 2 decimal places
        $hours = round($minutes / 60, 2);

        // Return formatted string with 2 decimal places and "hrs" suffix
        return number_format($hours, 2) . ' hrs';
    }

    /**
     * Format a dollar amount as a USD currency string.
     * Example: 1250.5 → "$1,250.50"
     *
     * @param float $amount The dollar amount to format
     * @return string Formatted currency string
     */
    public function formatCurrency(float $amount): string
    {
        // Use the currency symbol from config and format with commas
        return config('hws.currency_symbol') . number_format($amount, 2);
    }

    /**
     * Write a log entry to the HWS-specific log file.
     * All system operations should use this method for consistent logging.
     *
     * @param string $level   Log level: 'info', 'warning', 'error', 'debug'
     * @param string $message The log message
     * @param array  $context Optional context data to include with the log entry
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Get the configured log path from config/hws.php
        $logPath = config('hws.log_path');

        // Build a formatted log line with timestamp
        $timestamp = now()->toIso8601String();

        // Format the log entry as a readable string
        $logLine = "[{$timestamp}] [{$level}] {$message}";

        // Append context data if provided
        if (!empty($context)) {
            // JSON encode the context for structured logging
            $logLine .= ' ' . json_encode($context);
        }

        // Append a newline for file readability
        $logLine .= PHP_EOL;

        // Write to the HWS log file (append mode)
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

        // Also log to Laravel's default logger for redundancy
        Log::$level($message, $context);
    }

    /**
     * Extract a Google Sheet ID from a full URL or return the raw ID.
     * Handles multiple URL formats that users might paste in.
     *
     * @param string $input Full Google Sheets URL or plain sheet ID
     * @return string The extracted sheet ID
     */
    public function extractSheetId(string $input): string
    {
        // Trim any whitespace from the input
        $input = trim($input);

        // Check if the input contains a Google Sheets URL
        if (str_contains($input, 'docs.google.com/spreadsheets')) {
            // Extract the sheet ID using regex
            // Matches the ID between /d/ and the next / or end of string
            preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $input, $matches);

            // Return the captured group if found, otherwise the original input
            return $matches[1] ?? $input;
        }

        // Input is already a plain sheet ID — return as-is
        return $input;
    }

    /**
     * Parse a comma-separated string into a trimmed array.
     * Used for parsing Stripe customer IDs from the importer textarea,
     * and CC email addresses from template fields.
     *
     * @param string $input Comma-separated string
     * @return array<string> Array of trimmed, non-empty values
     */
    public function parseCommaSeparated(string $input): array
    {
        // Split the string by commas
        $parts = explode(',', $input);

        // Trim whitespace from each part
        $trimmed = array_map('trim', $parts);

        // Remove any empty strings that resulted from trailing commas or double commas
        $filtered = array_filter($trimmed, fn($value) => $value !== '');

        // Re-index the array (array_filter preserves keys)
        return array_values($filtered);
    }

    /**
     * Generate a formatted HTML table from an array of line items.
     * Used for the {{work_log}} shortcode in email templates.
     *
     * @param array $lineItems Array of line item data (each with date, description, time_minutes, employee_name)
     * @return string HTML table markup
     */
    public function generateWorkLogHtml(array $lineItems): string
    {
        // Start the HTML table with inline styles for email compatibility
        $html = '<table style="width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:14px;">';

        // Table header row
        $html .= '<thead>';
        $html .= '<tr style="background-color:#f2f2f2;">';
        $html .= '<th style="padding:8px; border:1px solid #ddd; text-align:left;">Date</th>';
        $html .= '<th style="padding:8px; border:1px solid #ddd; text-align:left;">Description</th>';
        $html .= '<th style="padding:8px; border:1px solid #ddd; text-align:right;">Time</th>';
        $html .= '<th style="padding:8px; border:1px solid #ddd; text-align:left;">Team Member</th>';
        $html .= '</tr>';
        $html .= '</thead>';

        // Table body — one row per line item
        $html .= '<tbody>';

        // Track total minutes for the summary row
        $totalMinutes = 0;

        // Loop through each line item and create a table row
        foreach ($lineItems as $item) {
            // Add this item's minutes to the running total
            $totalMinutes += $item['time_minutes'];

            // Format the time as hours with 2 decimal places
            $hours = number_format($item['time_minutes'] / 60, 2);

            // Build the row with inline styles for email compatibility
            $html .= '<tr>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($item['date']) . '</td>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($item['description'] ?? '') . '</td>';
            $html .= '<td style="padding:8px; border:1px solid #ddd; text-align:right;">' . $hours . ' hrs</td>';
            $html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($item['employee_name'] ?? '') . '</td>';
            $html .= '</tr>';
        }

        // Summary row with total hours
        $totalHours = number_format($totalMinutes / 60, 2);
        $html .= '<tr style="background-color:#f2f2f2; font-weight:bold;">';
        $html .= '<td style="padding:8px; border:1px solid #ddd;" colspan="2">Total</td>';
        $html .= '<td style="padding:8px; border:1px solid #ddd; text-align:right;">' . $totalHours . ' hrs</td>';
        $html .= '<td style="padding:8px; border:1px solid #ddd;"></td>';
        $html .= '</tr>';

        // Close the table
        $html .= '</tbody>';
        $html .= '</table>';

        // Return the complete HTML table
        return $html;
    }

    /**
     * Get status of connected services (Stripe accounts, Brevo/SMTP).
     * Used by Dashboard to show integration health at a glance.
     *
     * @return array{stripe: array, brevo: array}
     */
    public function getServiceStatus(): array
    {
        // Stripe accounts status
        $stripeAccounts = \App\Models\StripeAccount::orderBy('name')->get()->map(function ($acct) {
            return [
                'id'         => $acct->id,
                'name'       => $acct->name,
                'is_active'  => $acct->is_active,
                'is_default' => $acct->is_default,
                'masked_key' => $acct->masked_key,
            ];
        })->toArray();

        $hasActiveStripe = \App\Models\StripeAccount::where('is_active', true)->exists();
        $hasEnvStripe = !empty(config('hws.stripe.secret_key'));

        // Brevo/SMTP config status
        $smtpHost = config('mail.mailers.smtp.host', '');
        $smtpPort = config('mail.mailers.smtp.port', '');
        $smtpUser = config('mail.mailers.smtp.username', '');
        $fromAddr = config('mail.from.address', '');

        return [
            'stripe' => [
                'accounts'    => $stripeAccounts,
                'has_active'  => $hasActiveStripe,
                'has_env_key' => $hasEnvStripe,
                'configured'  => $hasActiveStripe || $hasEnvStripe,
            ],
            'brevo' => [
                'host'       => $smtpHost,
                'port'       => $smtpPort,
                'username'   => $smtpUser ? substr($smtpUser, 0, 4) . '...' : '',
                'from'       => $fromAddr,
                'configured' => !empty($smtpHost) && !empty($smtpUser) && !empty($fromAddr),
            ],
        ];
    }
}

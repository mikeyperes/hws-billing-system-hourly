<?php

/**
 * HWS - Hourly Bill Tracking System
 * ===========================================
 * Central configuration file.
 * ALL system constants and defaults live here.
 * NO hardcoded values should exist anywhere else in the codebase.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | General System Settings
    |--------------------------------------------------------------------------
    */

    // Application display name — shown in browser title and email templates
    'app_name' => env('HWS_APP_NAME', 'HWS - Hourly Bill Tracking System'),

    // Company name — used in email templates via {{company_name}} shortcode
    'company_name' => env('HWS_COMPANY_NAME', 'Hexa Web Systems'),

    // Currency code for Stripe API calls (ISO 4217)
    'currency' => 'USD',

    // Currency symbol for display formatting (prepended to amounts)
    'currency_symbol' => '$',

    // Application timezone — used for log timestamps and date formatting
    'timezone' => env('HWS_TIMEZONE', 'America/New_York'),

    /*
    |--------------------------------------------------------------------------
    | Billing Defaults
    |--------------------------------------------------------------------------
    */

    // Default hourly rate in USD — applied to new clients on import
    'default_hourly_rate' => (float) env('HWS_DEFAULT_HOURLY_RATE', 100.00),

    // Credit balance threshold in hours — flags clients when balance drops below this
    'credit_low_threshold_hours' => (float) env('HWS_CREDIT_LOW_THRESHOLD', 4.0),

    /*
    |--------------------------------------------------------------------------
    | Google Sheet Column Names
    | These must match the header row (Row 1) of every employee sheet exactly.
    |--------------------------------------------------------------------------
    */

    'sheet_columns' => [
        // Column A: auto-incrementing integer — used as scan cursor
        'primary_key' => 'primary_key',
        // Column B: date the work was performed (any format parseable by PHP)
        'date' => 'date',
        // Column C: duration in minutes (integer)
        'time' => 'time',
        // Column D: billing status — must be 'pending' to be collected
        'billed_status' => 'billed_status',
        // Column E: free-text work description
        'description' => 'description',
        // Column F: client name — must exactly match a client name in the system
        'client' => 'client',
        // Column G: domain/project reference — stored but not processed in billing logic
        'domain' => 'domain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Billed Status Values
    | Used when reading from and writing to employee Google Sheets.
    |--------------------------------------------------------------------------
    */

    'billed_status' => [
        // Status value for rows awaiting billing — only these are collected during scan
        'pending' => 'pending',
        // Status value written to the sheet after an invoice is marked as billed
        'billed' => 'billed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Status Lifecycle
    | Maps internal status names used throughout the codebase.
    |--------------------------------------------------------------------------
    */

    'invoice_statuses' => [
        // Initial status — invoice created on Stripe but not yet sent
        'draft' => 'draft',
        // Invoice has been finalized and/or sent to the client
        'sent' => 'sent',
        // Client has paid — confirmed by polling Stripe
        'paid' => 'paid',
        // Invoice cancelled or uncollectible
        'void' => 'void',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe API Configuration
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        // Stripe secret API key — starts with sk_test_ or sk_live_
        'secret_key' => env('STRIPE_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Sheets API Configuration
    |--------------------------------------------------------------------------
    */

    'google' => [
        // Absolute path to the Google service account JSON credentials file
        'credentials_path' => env('GOOGLE_CREDENTIALS_PATH', storage_path('app/google-credentials.json')),
        // Service account email — sheets must be shared with this email as Editor
        'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email / Brevo SMTP Configuration
    |--------------------------------------------------------------------------
    */

    'email' => [
        // Brevo SMTP server hostname
        'smtp_host' => env('HWS_SMTP_HOST', 'smtp-relay.brevo.com'),
        // Brevo SMTP port (587 for TLS)
        'smtp_port' => (int) env('HWS_SMTP_PORT', 587),
        // Brevo SMTP username (login email)
        'smtp_username' => env('HWS_SMTP_USERNAME', ''),
        // Brevo SMTP password (API key)
        'smtp_password' => env('HWS_SMTP_PASSWORD', ''),
        // Default sender display name for emails
        'from_name' => env('HWS_FROM_NAME', 'Hexa Web Systems'),
        // Default sender email address
        'from_email' => env('HWS_FROM_EMAIL', 'billing@hexawebsystems.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Shortcodes Reference
    | List of all available shortcodes for email templates.
    | Key = shortcode string, Value = human-readable description.
    |--------------------------------------------------------------------------
    */

    'shortcodes' => [
        // Client's display name from the clients table
        '{{client_name}}' => 'Client name',
        // Client's email address from the clients table
        '{{client_email}}' => 'Client email',
        // Formatted total amount of the invoice (e.g., "$1,250.00")
        '{{invoice_total}}' => 'Invoice total (formatted)',
        // Total hours of work in the invoice (e.g., "12.50")
        '{{invoice_hours}}' => 'Invoice hours',
        // Date the invoice was created (YYYY-MM-DD)
        '{{invoice_date}}' => 'Invoice creation date',
        // Stripe hosted URL where client can view and pay the invoice
        '{{invoice_stripe_url}}' => 'Stripe invoice URL',
        // HTML table of all line items — rendered by GenericService
        '{{work_log}}' => 'Work log HTML table (embedded in body)',
        // Client's remaining prepaid credit balance in hours
        '{{credit_balance}}' => 'Client credit balance (hours)',
        // Company name from settings
        '{{company_name}}' => 'Company name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Billed Cell Background Color (Google Sheets)
    | Light red applied to billed rows for visual feedback.
    | Values are 0.0–1.0 float (Google Sheets API format).
    |--------------------------------------------------------------------------
    */

    'billed_cell_color' => [
        // Red component (0.95 = very light red)
        'red' => 0.95,
        // Green component (0.8 = muted, not pure red)
        'green' => 0.8,
        // Blue component (0.8 = muted, not pure red)
        'blue' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default List Items
    | Seeded into the lists table on first run.
    | Format: 'list_key' => ['value1', 'value2', ...]
    |--------------------------------------------------------------------------
    */

    'default_lists' => [
        // Billing type options for the client billing_type dropdown
        'customer_billing_type' => [
            'hourly_open',    // Open hourly — billed per invoice with no prepaid balance
            'hourly_credits', // Prepaid hours — balance tracked and deducted per invoice
            'fixed',          // Fixed/retainer — label only, no billing processing
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    // Absolute path for the HWS-specific log file
    'log_path' => storage_path('logs/hws.log'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    // Default number of records per page in list views
    'per_page' => 25,

];

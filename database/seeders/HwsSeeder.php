<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ListItem;
use App\Models\EmailTemplate;
use App\Models\Setting;

/**
 * Seeds the database with initial data required for first run.
 * Creates: default admin user, billing type list items, starter email templates,
 * and default settings entries.
 */
class HwsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // --------------------------------------------------
        // 1. Create default admin user
        // --------------------------------------------------
        // Only create if no users exist (prevents duplicate on re-seed)
        if (User::count() === 0) {
            User::create([
                // Default admin name — change after first login
                'name' => 'Admin',
                // Default admin email — change after first login
                'email' => 'admin@hexawebsystems.com',
                // Default password — MUST be changed after first login
                'password' => Hash::make('changeme123'),
                // Role for future multi-user support
                'role' => 'admin',
            ]);
        }

        // --------------------------------------------------
        // 2. Seed default list items from config
        // --------------------------------------------------
        // Pull the default lists defined in config/hws.php
        $defaultLists = config('hws.default_lists', []);

        // Loop through each list key and its values
        foreach ($defaultLists as $listKey => $values) {
            // Loop through each value within this list key
            foreach ($values as $sortIndex => $value) {
                // Only create if this exact key+value combo doesn't already exist
                ListItem::firstOrCreate(
                    [
                        // Match on both key and value to prevent duplicates
                        'list_key' => $listKey,
                        'list_value' => $value,
                    ],
                    [
                        // Sort order based on array position
                        'sort_order' => $sortIndex,
                        // All seeded items start as active
                        'is_active' => true,
                    ]
                );
            }
        }

        // --------------------------------------------------
        // 3. Seed default email templates
        // --------------------------------------------------

        // Invoice notification template — primary template for sending invoice emails
        EmailTemplate::firstOrCreate(
            [
                // Match on use_case and name to prevent duplicates
                'use_case' => 'invoice_notification',
                'name' => 'Standard Invoice Notification',
            ],
            [
                // This is the default template for invoice notifications
                'is_primary' => true,
                // Sender name uses company name shortcode
                'from_name' => '{{company_name}}',
                // Sender email — will be replaced by settings value at send time
                'from_email' => config('hws.email.from_email'),
                // Reply-to same as from
                'reply_to' => config('hws.email.from_email'),
                // No CC by default
                'cc' => null,
                // Subject line with client name and invoice total shortcodes
                'subject' => 'Invoice from {{company_name}} — {{invoice_total}}',
                // HTML body with work log shortcode for line item details
                'body' => '<p>Hi {{client_name}},</p>'
                    . '<p>Please find your invoice for {{invoice_hours}} hours of work totaling {{invoice_total}}.</p>'
                    . '<p>You can view and pay your invoice here: <a href="{{invoice_stripe_url}}">View Invoice</a></p>'
                    . '<h3>Work Log</h3>'
                    . '{{work_log}}'
                    . '<p>Thank you for your business.</p>'
                    . '<p>{{company_name}}</p>',
                // Template is active and available for selection
                'is_active' => true,
            ]
        );

        // Low credit alert template — for notifying clients when prepaid hours are running low
        EmailTemplate::firstOrCreate(
            [
                // Match on use_case and name to prevent duplicates
                'use_case' => 'low_credit_alert',
                'name' => 'Low Credit Balance Alert',
            ],
            [
                // This is the default template for low credit alerts
                'is_primary' => true,
                // Sender name uses company name shortcode
                'from_name' => '{{company_name}}',
                // Sender email from config
                'from_email' => config('hws.email.from_email'),
                // Reply-to same as from
                'reply_to' => config('hws.email.from_email'),
                // No CC by default
                'cc' => null,
                // Subject line indicating low balance
                'subject' => 'Credit Balance Update — {{company_name}}',
                // HTML body showing remaining balance and prompting action
                'body' => '<p>Hi {{client_name}},</p>'
                    . '<p>This is a friendly reminder that your prepaid credit balance is running low.</p>'
                    . '<p><strong>Remaining Balance:</strong> {{credit_balance}} hours</p>'
                    . '<p>Please let us know if you\'d like to purchase additional hours.</p>'
                    . '<p>Thank you,<br>{{company_name}}</p>',
                // Template is active and available for selection
                'is_active' => true,
            ]
        );

        // --------------------------------------------------
        // 4. Seed default settings entries
        // --------------------------------------------------

        // Each setting: key, default value, group, input type, label, sort order
        $defaultSettings = [
            // Email settings group
            ['key' => 'smtp_host', 'value' => config('hws.email.smtp_host'), 'group' => 'email', 'type' => 'text', 'label' => 'SMTP Host', 'sort_order' => 1],
            ['key' => 'smtp_port', 'value' => (string) config('hws.email.smtp_port'), 'group' => 'email', 'type' => 'text', 'label' => 'SMTP Port', 'sort_order' => 2],
            ['key' => 'smtp_username', 'value' => config('hws.email.smtp_username'), 'group' => 'email', 'type' => 'text', 'label' => 'SMTP Username', 'sort_order' => 3],
            ['key' => 'smtp_password', 'value' => config('hws.email.smtp_password'), 'group' => 'email', 'type' => 'password', 'label' => 'SMTP Password (API Key)', 'sort_order' => 4],
            ['key' => 'from_name', 'value' => config('hws.email.from_name'), 'group' => 'email', 'type' => 'text', 'label' => 'From Name', 'sort_order' => 5],
            ['key' => 'from_email', 'value' => config('hws.email.from_email'), 'group' => 'email', 'type' => 'text', 'label' => 'From Email', 'sort_order' => 6],
            // Google settings group
            ['key' => 'google_service_account_email', 'value' => config('hws.google.service_account_email'), 'group' => 'google', 'type' => 'text', 'label' => 'Service Account Email', 'sort_order' => 1],
            // System settings group
            ['key' => 'company_name', 'value' => config('hws.company_name'), 'group' => 'system', 'type' => 'text', 'label' => 'Company Name', 'sort_order' => 1],
            ['key' => 'default_hourly_rate', 'value' => (string) config('hws.default_hourly_rate'), 'group' => 'system', 'type' => 'text', 'label' => 'Default Hourly Rate ($)', 'sort_order' => 2],
            ['key' => 'credit_low_threshold', 'value' => (string) config('hws.credit_low_threshold_hours'), 'group' => 'system', 'type' => 'text', 'label' => 'Low Credit Threshold (hours)', 'sort_order' => 3],
        ];

        // Loop through and create each setting if it doesn't exist
        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                // Match on key to prevent duplicates
                ['key' => $setting['key']],
                // All other fields as defaults
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                    'label' => $setting['label'],
                    'sort_order' => $setting['sort_order'],
                ]
            );
        }
    }
}

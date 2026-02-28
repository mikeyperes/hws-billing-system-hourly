<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

/**
 * SettingController — manages runtime-editable system settings.
 * Settings are grouped by category (email, google, system).
 * Also handles SMTP test email functionality.
 */
class SettingController extends Controller
{
    /**
     * @var EmailService Email service for test email functionality
     */
    protected EmailService $email;

    /**
     * Constructor — inject the EmailService.
     *
     * @param EmailService $email Email sending service
     */
    public function __construct(EmailService $email)
    {
        // Store the email service reference
        $this->email = $email;
    }

    /**
     * Display the settings page with all settings grouped by category.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all settings grouped by their category
        $groupedSettings = Setting::getGrouped();

        // Build server info for the system information display
        $serverInfo = [
            // PHP version currently running
            'php_version' => phpversion(),
            // Server operating system
            'server_os' => php_uname('s') . ' ' . php_uname('r'),
            // Laravel version
            'laravel_version' => app()->version(),
            // Available disk space on the partition
            'disk_free' => $this->formatBytes(disk_free_space('/')),
            // Total disk space
            'disk_total' => $this->formatBytes(disk_total_space('/')),
            // Server uptime (Linux only)
            'uptime' => $this->getUptime(),
            // Service account email for quick reference (for sharing sheets)
            'google_service_account' => config('hws.google.service_account_email'),
        ];

        // Render the settings view
        return view('settings.index', [
            'groupedSettings' => $groupedSettings,  // Settings by category
            'serverInfo'      => $serverInfo,        // Server information
        ]);
    }

    /**
     * Update settings values from the form submission.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        // Get the settings array from the form (keyed by setting key)
        $settings = $request->input('settings', []);

        // Loop through each submitted setting and update its value
        foreach ($settings as $key => $value) {
            // Update the setting value in the database
            Setting::where('key', $key)->update(['value' => $value]);
        }

        // Redirect back to the settings page with success message
        return redirect()
            ->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    /**
     * Send a test email to verify SMTP configuration.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function testEmail(Request $request)
    {
        // Validate the test email address
        $validated = $request->validate([
            'test_email' => 'required|email',  // Must be a valid email address
        ]);

        // Build a test email body
        $testBody = '<h2>HWS SMTP Test</h2>'
            . '<p>This is a test email from the HWS billing system.</p>'
            . '<p>If you are reading this, your SMTP configuration is working correctly.</p>'
            . '<p>Sent at: ' . now()->toDateTimeString() . '</p>';

        // Send the test email using the single EmailService
        $result = $this->email->send(
            $validated['test_email'],      // Recipient
            'HWS SMTP Test',              // Subject
            $testBody                      // HTML body
            // from_name, from_email, reply_to, cc all use defaults
        );

        // Determine the redirect message type
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back with the result
        return redirect()
            ->route('settings.index')
            ->with($messageType, $result['message']);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], Auth::user()->password)) {
            return redirect()
                ->route('settings.index')
                ->with('error', 'Current password is incorrect.');
        }

        Auth::user()->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Format bytes into a human-readable string.
     *
     * @param float $bytes Number of bytes
     * @return string Formatted string (e.g., "45.2 GB")
     */
    protected function formatBytes(float $bytes): string
    {
        // Define the unit suffixes
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        // Start at bytes
        $unitIndex = 0;

        // Divide by 1024 until we find the right unit
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            // Divide by 1024 to move to the next unit
            $bytes /= 1024;
            // Increment the unit index
            $unitIndex++;
        }

        // Return formatted string with 1 decimal place
        return round($bytes, 1) . ' ' . $units[$unitIndex];
    }

    /**
     * Get server uptime as a human-readable string (Linux only).
     *
     * @return string Uptime string or 'N/A' if unavailable
     */
    protected function getUptime(): string
    {
        // Check if the /proc/uptime file exists (Linux only)
        if (!file_exists('/proc/uptime')) {
            // Not available on this OS
            return 'N/A';
        }

        // Read the uptime in seconds from /proc/uptime
        $uptimeSeconds = (float) file_get_contents('/proc/uptime');

        // Convert seconds to days, hours, minutes
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);

        // Build the human-readable string
        return "{$days}d {$hours}h {$minutes}m";
    }
}

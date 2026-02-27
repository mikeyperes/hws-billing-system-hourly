<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * ForgotPasswordController — handles forgot password and password reset flow.
 * Uses a custom implementation with our EmailService (PHPMailer/Brevo SMTP)
 * instead of Laravel's built-in password broker, so it works with whatever
 * SMTP is configured in the HWS settings.
 *
 * Flow:
 * 1. User clicks "Forgot Password" → showRequestForm()
 * 2. User submits email → sendResetLink() → generates token, sends email
 * 3. User clicks link in email → showResetForm()
 * 4. User submits new password → resetPassword() → updates user, deletes token
 */
class ForgotPasswordController extends Controller
{
    /**
     * @var EmailService Our custom email sender
     */
    protected EmailService $emailService;

    /**
     * Constructor — inject EmailService.
     *
     * @param EmailService $emailService Custom email sending service
     */
    public function __construct(EmailService $emailService)
    {
        // Store service reference
        $this->emailService = $emailService;
    }

    /**
     * Show the forgot password form.
     *
     * @return \Illuminate\View\View
     */
    public function showRequestForm()
    {
        // Render the email input form
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the user's email.
     * Generates a random token, stores it in password_reset_tokens table,
     * and sends an email with the reset link using our EmailService.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLink(Request $request)
    {
        // Validate the email field
        $request->validate([
            'email' => 'required|email',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Always show success message (even if email not found) to prevent enumeration
        if (!$user) {
            return redirect()
                ->back()
                ->with('status', 'If an account exists with that email, a reset link has been sent.');
        }

        // Generate a random token
        $token = Str::random(64);

        // Delete any existing tokens for this email
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        // Store the new token
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token), // Store hashed for security
            'created_at' => now(),
        ]);

        // Build the reset URL
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        // Build the email body
        $body = '<h2>Password Reset Request</h2>'
            . '<p>You requested a password reset for your ' . config('hws.app_name') . ' account.</p>'
            . '<p><a href="' . $resetUrl . '" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Reset Password</a></p>'
            . '<p style="color:#666;font-size:12px;">This link expires in 60 minutes. If you didn\'t request this, ignore this email.</p>'
            . '<p style="color:#999;font-size:11px;">Direct link: ' . $resetUrl . '</p>';

        // Send the email using our EmailService
        try {
            $result = $this->emailService->send(
                $user->email,                                   // To
                $user->name ?? 'User',                          // To name
                'Password Reset — ' . config('hws.app_name'),   // Subject
                $body                                           // HTML body
            );

            if (!$result['success']) {
                // Email sending failed — log and show error
                return redirect()
                    ->back()
                    ->with('error', 'Could not send reset email. Check SMTP settings. Error: ' . ($result['error'] ?? 'Unknown'));
            }
        } catch (\Exception $e) {
            // Exception during send — show error
            return redirect()
                ->back()
                ->with('error', 'Could not send reset email: ' . $e->getMessage());
        }

        // Success — redirect back with status
        return redirect()
            ->back()
            ->with('status', 'If an account exists with that email, a reset link has been sent.');
    }

    /**
     * Show the password reset form (user clicked the link in their email).
     *
     * @param Request $request
     * @param string  $token   The reset token from the URL
     * @return \Illuminate\View\View
     */
    public function showResetForm(Request $request, string $token)
    {
        // Render the reset form, passing token and email
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Reset the user's password.
     * Validates the token, updates the password, and deletes the token.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetPassword(Request $request)
    {
        // Validate all fields
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed', // Must match password_confirmation
        ]);

        // Look up the token record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Check if token record exists
        if (!$record) {
            return redirect()
                ->back()
                ->withInput(['email' => $request->email])
                ->withErrors(['email' => 'No password reset request found for this email.']);
        }

        // Check if token has expired (60 minutes)
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 60) {
            // Delete the expired token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return redirect()
                ->route('password.request')
                ->with('error', 'Reset link has expired. Please request a new one.');
        }

        // Verify the token matches (compare against hashed token)
        if (!Hash::check($request->token, $record->token)) {
            return redirect()
                ->back()
                ->withInput(['email' => $request->email])
                ->withErrors(['email' => 'Invalid reset token.']);
        }

        // Find the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return redirect()
                ->back()
                ->withErrors(['email' => 'No account found with this email.']);
        }

        // Update the password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete the used token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Redirect to login with success
        return redirect()
            ->route('login')
            ->with('success', 'Password reset successfully. Please sign in with your new password.');
    }
}

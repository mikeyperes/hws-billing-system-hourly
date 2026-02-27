<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\EmailTemplate;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * EmailService — THE single email sending function for the entire system.
 * All email sending throughout the codebase MUST go through this service.
 * Handles Brevo SMTP authentication, shortcode rendering, and template resolution.
 *
 * NO other file should contain email sending logic. This is enforced by spec.
 */
class EmailService
{
    /**
     * @var GenericService Shared utility service for logging and formatting
     */
    protected GenericService $generic;

    /**
     * Constructor — inject the shared GenericService.
     *
     * @param GenericService $generic Shared utility service
     */
    public function __construct(GenericService $generic)
    {
        // Store reference to the generic service for logging
        $this->generic = $generic;
    }

    /**
     * Send an email using Brevo SMTP.
     * This is THE email sending function for the entire system.
     *
     * @param string      $to       Recipient email address
     * @param string      $subject  Email subject line
     * @param string      $body     Email body (HTML)
     * @param string|null $fromName Sender display name (uses default from settings if null)
     * @param string|null $fromEmail Sender email address (uses default from settings if null)
     * @param string|null $replyTo  Reply-to email address (optional)
     * @param string|null $cc       CC email addresses, comma-separated (optional)
     * @return array{success: bool, message: string} Result with success flag and message
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        ?string $fromName = null,
        ?string $fromEmail = null,
        ?string $replyTo = null,
        ?string $cc = null
    ): array {
        try {
            // Create a new PHPMailer instance with exceptions enabled
            $mail = new PHPMailer(true);

            // ── SMTP Configuration ──
            // Enable SMTP sending (not PHP mail())
            $mail->isSMTP();

            // Get SMTP settings — prefer database settings, fall back to config/hws.php
            $smtpHost = Setting::getValue('smtp_host', config('hws.email.smtp_host'));
            $smtpPort = (int) Setting::getValue('smtp_port', config('hws.email.smtp_port'));
            $smtpUsername = Setting::getValue('smtp_username', config('hws.email.smtp_username'));
            $smtpPassword = Setting::getValue('smtp_password', config('hws.email.smtp_password'));

            // Set the SMTP server hostname
            $mail->Host = $smtpHost;
            // Enable SMTP authentication
            $mail->SMTPAuth = true;
            // SMTP username (Brevo login)
            $mail->Username = $smtpUsername;
            // SMTP password (Brevo API key)
            $mail->Password = $smtpPassword;
            // Use TLS encryption
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // Standard Brevo SMTP port
            $mail->Port = $smtpPort;

            // ── Sender Configuration ──
            // Use provided from values, or fall back to database settings, or config defaults
            $senderName = $fromName ?? Setting::getValue('from_name', config('hws.email.from_name'));
            $senderEmail = $fromEmail ?? Setting::getValue('from_email', config('hws.email.from_email'));

            // Set the sender address and display name
            $mail->setFrom($senderEmail, $senderName);

            // Set the recipient address
            $mail->addAddress($to);

            // Set reply-to if provided
            if ($replyTo) {
                // Add reply-to address
                $mail->addReplyTo($replyTo);
            }

            // Set CC recipients if provided (comma-separated string)
            if ($cc) {
                // Parse the comma-separated CC string into individual addresses
                $ccAddresses = $this->generic->parseCommaSeparated($cc);
                // Add each CC address
                foreach ($ccAddresses as $ccAddress) {
                    $mail->addCC($ccAddress);
                }
            }

            // ── Email Content ──
            // Enable HTML email
            $mail->isHTML(true);
            // Set the subject line
            $mail->Subject = $subject;
            // Set the HTML body
            $mail->Body = $body;
            // Set plain text fallback by stripping HTML tags
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            // Set UTF-8 charset for proper character encoding
            $mail->CharSet = 'UTF-8';

            // ── Send ──
            // Attempt to send the email
            $mail->send();

            // Log the successful send
            $this->generic->log('info', 'Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
            ]);

            // Return success response
            return [
                'success' => true,
                'message' => 'Email sent successfully to ' . $to,
            ];

        } catch (PHPMailerException $e) {
            // Log the email failure with error details
            $this->generic->log('error', 'Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            // Return failure response with error message
            return [
                'success' => false,
                'message' => 'Email failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Render shortcodes in a text string by replacing {{key}} with actual values.
     * Used on all template fields: subject, body, from_name, etc.
     *
     * @param string $text       The text containing shortcodes
     * @param array  $shortcodes Key-value pairs where keys are shortcode strings (e.g., '{{client_name}}')
     * @return string The text with all shortcodes replaced
     */
    public function renderShortcodes(string $text, array $shortcodes): string
    {
        // Loop through each shortcode and replace it in the text
        foreach ($shortcodes as $code => $value) {
            // Replace all occurrences of this shortcode with its value
            $text = str_replace($code, $value ?? '', $text);
        }

        // Return the fully rendered text
        return $text;
    }

    /**
     * Build shortcode values for a given invoice context.
     * Resolves all shortcodes defined in config/hws.php to their actual values.
     *
     * @param \App\Models\Invoice $invoice   The invoice providing context
     * @param \App\Models\Client  $client    The client for this invoice
     * @param string              $workLogHtml Pre-rendered work log HTML table
     * @return array<string, string> Shortcode-to-value mapping
     */
    public function buildInvoiceShortcodes(
        \App\Models\Invoice $invoice,
        \App\Models\Client $client,
        string $workLogHtml = ''
    ): array {
        // Build and return the complete shortcode mapping
        return [
            // Client information
            '{{client_name}}'       => $client->name,
            '{{client_email}}'      => $client->email ?? '',
            // Invoice information
            '{{invoice_total}}'     => $invoice->formatted_amount,
            '{{invoice_hours}}'     => (string) $invoice->total_hours,
            '{{invoice_date}}'      => $invoice->created_at->format('Y-m-d'),
            '{{invoice_stripe_url}}' => $invoice->stripe_invoice_url ?? '#',
            // Work log table HTML
            '{{work_log}}'          => $workLogHtml,
            // Client credit balance
            '{{credit_balance}}'    => (string) $client->credit_balance_hours,
            // System company name from settings
            '{{company_name}}'      => Setting::getValue('company_name', config('hws.company_name')),
        ];
    }

    /**
     * Send an email using a specific template with shortcode substitution.
     * Resolves the template, renders shortcodes in all fields, and sends.
     *
     * @param EmailTemplate $template   The email template to use
     * @param string        $toEmail    Recipient email address
     * @param array         $shortcodes Shortcode-to-value mapping for rendering
     * @return array{success: bool, message: string} Result with success flag and message
     */
    public function sendFromTemplate(
        EmailTemplate $template,
        string $toEmail,
        array $shortcodes
    ): array {
        // Render shortcodes in all template fields
        $subject   = $this->renderShortcodes($template->subject ?? '', $shortcodes);
        $body      = $this->renderShortcodes($template->body ?? '', $shortcodes);
        $fromName  = $this->renderShortcodes($template->from_name ?? '', $shortcodes);
        $fromEmail = $this->renderShortcodes($template->from_email ?? '', $shortcodes);
        $replyTo   = $this->renderShortcodes($template->reply_to ?? '', $shortcodes);
        $cc        = $this->renderShortcodes($template->cc ?? '', $shortcodes);

        // Delegate to the single send() method
        return $this->send(
            $toEmail,
            $subject,
            $body,
            $fromName ?: null,  // Convert empty string to null for default handling
            $fromEmail ?: null, // Convert empty string to null for default handling
            $replyTo ?: null,   // Convert empty string to null
            $cc ?: null         // Convert empty string to null
        );
    }
}

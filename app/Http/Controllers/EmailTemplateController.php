<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Http\Request;

/**
 * EmailTemplateController — manages email templates with preview and test functionality.
 * Templates support shortcodes, multiple templates per use case, and primary selection.
 */
class EmailTemplateController extends Controller
{
    /**
     * @var EmailService Email service for test sends and shortcode rendering
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
     * Display the email template management page.
     * Templates are grouped by use case for organized display.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all templates grouped by use_case
        $templates = EmailTemplate::orderBy('use_case')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->groupBy('use_case');

        // Get the shortcode reference list from config
        $shortcodes = config('hws.shortcodes');

        // Render the template list view
        return view('emails.index', [
            'templates'  => $templates,   // Templates grouped by use case
            'shortcodes' => $shortcodes,  // Shortcode reference
        ]);
    }

    /**
     * Display the create template form.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // Get existing use cases for the dropdown (user can also type a new one)
        $useCases = EmailTemplate::getUseCases();

        // Get the shortcode reference list from config
        $shortcodes = config('hws.shortcodes');

        // Render the create form
        return view('emails.create', [
            'useCases'   => $useCases,    // Existing use case options
            'shortcodes' => $shortcodes,  // Shortcode reference
        ]);
    }

    /**
     * Store a new email template.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'use_case'   => 'required|string|max:100',    // Use case category
            'name'       => 'required|string|max:255',    // Template name
            'from_name'  => 'nullable|string|max:255',    // Sender name
            'from_email' => 'nullable|string|max:255',    // Sender email
            'reply_to'   => 'nullable|string|max:255',    // Reply-to
            'cc'         => 'nullable|string|max:500',    // CC addresses
            'subject'    => 'nullable|string|max:500',    // Subject line
            'body'       => 'nullable|string',            // HTML body
        ]);

        // Create the template record
        $template = EmailTemplate::create([
            'use_case'   => $validated['use_case'],            // Use case
            'name'       => $validated['name'],                // Name
            'from_name'  => $validated['from_name'] ?? null,   // Sender name
            'from_email' => $validated['from_email'] ?? null,  // Sender email
            'reply_to'   => $validated['reply_to'] ?? null,    // Reply-to
            'cc'         => $validated['cc'] ?? null,          // CC
            'subject'    => $validated['subject'] ?? null,     // Subject
            'body'       => $validated['body'] ?? null,        // Body
            'is_primary' => false,                             // Not primary by default
            'is_active'  => true,                              // Active by default
        ]);

        // Redirect to the template list with success message
        return redirect()
            ->route('emails.index')
            ->with('success', 'Template "' . $template->name . '" created.');
    }

    /**
     * Display the edit form for an existing template.
     *
     * @param EmailTemplate $email Route model binding (parameter name matches route)
     * @return \Illuminate\View\View
     */
    public function edit(EmailTemplate $email)
    {
        // Get existing use cases for the dropdown
        $useCases = EmailTemplate::getUseCases();

        // Get the shortcode reference list from config
        $shortcodes = config('hws.shortcodes');

        // Render the edit form
        return view('emails.edit', [
            'template'   => $email,       // The template being edited
            'useCases'   => $useCases,    // Use case dropdown options
            'shortcodes' => $shortcodes,  // Shortcode reference
        ]);
    }

    /**
     * Update an existing email template.
     *
     * @param Request       $request
     * @param EmailTemplate $email   Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, EmailTemplate $email)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'use_case'   => 'required|string|max:100',
            'name'       => 'required|string|max:255',
            'from_name'  => 'nullable|string|max:255',
            'from_email' => 'nullable|string|max:255',
            'reply_to'   => 'nullable|string|max:255',
            'cc'         => 'nullable|string|max:500',
            'subject'    => 'nullable|string|max:500',
            'body'       => 'nullable|string',
            'is_active'  => 'boolean',
        ]);

        // Update the template record
        $email->update([
            'use_case'   => $validated['use_case'],
            'name'       => $validated['name'],
            'from_name'  => $validated['from_name'] ?? null,
            'from_email' => $validated['from_email'] ?? null,
            'reply_to'   => $validated['reply_to'] ?? null,
            'cc'         => $validated['cc'] ?? null,
            'subject'    => $validated['subject'] ?? null,
            'body'       => $validated['body'] ?? null,
            'is_active'  => $request->has('is_active'),
        ]);

        // Redirect back to the edit page with success message
        return redirect()
            ->route('emails.edit', $email)
            ->with('success', 'Template updated.');
    }

    /**
     * Set a template as the primary for its use case.
     * Unsets any other primary template for the same use case.
     *
     * @param EmailTemplate $email Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function makePrimary(EmailTemplate $email)
    {
        // Delegate to the model method which handles unsetting the old primary
        $email->makePrimary();

        // Redirect back with success message
        return redirect()
            ->route('emails.index')
            ->with('success', '"' . $email->name . '" is now the primary template for ' . $email->use_case . '.');
    }

    /**
     * Send a test email using a template with sample shortcode data.
     *
     * @param Request       $request
     * @param EmailTemplate $email   Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function testSend(Request $request, EmailTemplate $email)
    {
        // Validate the test email address
        $validated = $request->validate([
            'test_email' => 'required|email',  // Must be a valid email
        ]);

        // Build sample shortcode data for testing
        $sampleShortcodes = [
            '{{client_name}}'        => 'Sample Client',
            '{{client_email}}'       => 'sample@example.com',
            '{{invoice_total}}'      => '$500.00',
            '{{invoice_hours}}'      => '5.00',
            '{{invoice_date}}'       => now()->format('Y-m-d'),
            '{{invoice_stripe_url}}' => 'https://stripe.com/sample-invoice',
            '{{work_log}}'           => '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f2f2f2;"><th style="padding:8px;border:1px solid #ddd;">Date</th><th style="padding:8px;border:1px solid #ddd;">Description</th><th style="padding:8px;border:1px solid #ddd;">Time</th><th style="padding:8px;border:1px solid #ddd;">Team Member</th></tr><tr><td style="padding:8px;border:1px solid #ddd;">2026-02-27</td><td style="padding:8px;border:1px solid #ddd;">Sample task description</td><td style="padding:8px;border:1px solid #ddd;">2.50 hrs</td><td style="padding:8px;border:1px solid #ddd;">John Doe</td></tr></table>',
            '{{credit_balance}}'     => '10.50',
            '{{company_name}}'       => config('hws.company_name'),
        ];

        // Send the test email using the template with sample data
        $result = $this->email->sendFromTemplate($email, $validated['test_email'], $sampleShortcodes);

        // Determine the redirect message type
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back with the result
        return redirect()
            ->route('emails.edit', $email)
            ->with($messageType, 'Test: ' . $result['message']);
    }

    /**
     * Delete an email template.
     *
     * @param EmailTemplate $email Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(EmailTemplate $email)
    {
        // Store the name for the success message before deleting
        $name = $email->name;

        // Delete the template record
        $email->delete();

        // Redirect to the template list with success message
        return redirect()
            ->route('emails.index')
            ->with('success', 'Template "' . $name . '" deleted.');
    }
}

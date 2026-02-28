<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\EmailTemplate;
use App\Services\BillingService;
use App\Services\StripeService;
use App\Services\EmailService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * InvoiceController — manages invoices and their lifecycle actions.
 * Handles: listing, viewing line items, marking as billed, reversing billing,
 * refreshing payment status, sending emails, and Stripe finalize/send.
 */
class InvoiceController extends Controller
{
    /**
     * @var BillingService Orchestrates billing operations
     */
    protected BillingService $billing;

    /**
     * @var StripeService Stripe API interactions
     */
    protected StripeService $stripe;

    /**
     * @var EmailService Email sending with shortcodes
     */
    protected EmailService $email;

    /**
     * @var GenericService Shared utilities
     */
    protected GenericService $generic;

    /**
     * Constructor — inject all required services.
     *
     * @param BillingService $billing Billing orchestration service
     * @param StripeService  $stripe  Stripe API service
     * @param EmailService   $email   Email sending service
     * @param GenericService $generic Shared utility service
     */
    public function __construct(
        BillingService $billing,
        StripeService $stripe,
        EmailService $email,
        GenericService $generic
    ) {
        // Store service references
        $this->billing = $billing;
        $this->stripe = $stripe;
        $this->email = $email;
        $this->generic = $generic;
    }

    /**
     * Display the invoice list page.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Build the query with client relationship eager-loaded
        $query = Invoice::with('client');

        // Filter by status if provided in the query string
        if ($request->has('status') && $request->input('status') !== '') {
            // Apply status filter
            $query->where('status', $request->input('status'));
        }

        // Get the paginated results, newest first
        $invoices = $query->orderByDesc('created_at')
            ->paginate(config('hws.per_page'));

        // Render the invoice list view
        return view('invoices.index', [
            'invoices'      => $invoices,                        // Paginated invoices
            'currentStatus' => $request->input('status', ''),    // Active filter for UI highlighting
        ]);
    }

    /**
     * Display the line items for a specific invoice.
     * Clean, read-only view organized by employee.
     *
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\View\View
     */
    public function show(Invoice $invoice)
    {
        // Eager-load line items with their employee relationships
        $invoice->load(['lineItems.employee', 'client']);

        // Group line items by employee for organized display
        $itemsByEmployee = $invoice->lineItems->groupBy('employee_id');

        // Render the invoice detail view
        return view('invoices.show', [
            'invoice'         => $invoice,          // The invoice
            'itemsByEmployee' => $itemsByEmployee,   // Line items grouped by employee
        ]);
    }

    /**
     * Mark an invoice as billed.
     * Updates employee sheet rows to "billed" and updates scan cursors.
     *
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function markBilled(Invoice $invoice)
    {
        // Delegate to the BillingService
        $result = $this->billing->markAsBilled($invoice);

        // Determine the redirect message type based on success
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back to the invoice list with the result message
        return redirect()
            ->route('invoices.index')
            ->with($messageType, $result['message']);
    }

    /**
     * Reverse billing for an invoice.
     * Resets employee sheet rows and rolls back scan cursors.
     *
     * @param Request $request
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reverseBilling(Request $request, Invoice $invoice)
    {
        // Get the status to reset rows to (default: pending)
        $resetStatus = $request->input('reset_status', config('hws.billed_status.pending'));

        // Delegate to the BillingService
        $result = $this->billing->reverseBilling($invoice, $resetStatus);

        // Determine the redirect message type
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back with the result message
        return redirect()
            ->route('invoices.index')
            ->with($messageType, $result['message']);
    }

    /**
     * Refresh payment status for a single invoice from Stripe.
     *
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function refreshStatus(Invoice $invoice)
    {
        // Delegate to the BillingService
        $result = $this->billing->refreshPaymentStatus($invoice);

        // Determine the redirect message type
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back with the result message
        return redirect()
            ->route('invoices.index')
            ->with($messageType, $result['message']);
    }

    /**
     * Refresh payment status for ALL unpaid invoices from Stripe.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function refreshAllUnpaid()
    {
        // Get all unpaid invoices that have a Stripe invoice ID
        $unpaidInvoices = Invoice::unpaid()
            ->whereNotNull('stripe_invoice_id')
            ->get();

        // Track results
        $updated = 0;   // Count of successfully updated invoices
        $errors = 0;    // Count of failed updates

        // Process each unpaid invoice
        foreach ($unpaidInvoices as $invoice) {
            // Refresh this invoice's status
            $result = $this->billing->refreshPaymentStatus($invoice);

            // Count successes and failures
            if ($result['success']) {
                $updated++;
            } else {
                $errors++;
            }
        }

        // Build the result message
        $message = "Refreshed {$updated} invoices.";
        // Append error count if any failed
        if ($errors > 0) {
            $message .= " {$errors} failed.";
        }

        // Redirect back to the invoice list with the result
        return redirect()
            ->route('invoices.index')
            ->with('success', $message);
    }

    /**
     * Display the email compose form for an invoice.
     * Pre-populates with client info and primary email template.
     *
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\View\View
     */
    public function showEmail(Invoice $invoice)
    {
        // Load the client relationship
        $invoice->load('client');

        // Get available email templates for invoice notifications
        $templates = EmailTemplate::getByUseCase('invoice_notification');

        // Get the primary template (or first available)
        $primaryTemplate = EmailTemplate::getPrimary('invoice_notification');

        // Build shortcodes for this invoice context
        // First, generate the work log HTML from line items
        $lineItems = $invoice->lineItems()->with('employee')->get();
        // Map line items to the format expected by GenericService
        $workLogData = $lineItems->map(function ($item) {
            return [
                'date'          => $item->date->format('Y-m-d'),  // Formatted date
                'description'   => $item->description,             // Work description
                'time_minutes'  => $item->time_minutes,            // Duration in minutes
                'employee_name' => $item->employee->name ?? '',    // Employee name
            ];
        })->toArray();

        // Generate the HTML work log table
        $workLogHtml = $this->generic->generateWorkLogHtml($workLogData);

        // Build the full shortcode mapping
        $shortcodes = $this->email->buildInvoiceShortcodes($invoice, $invoice->client, $workLogHtml);

        // Render the email compose view
        return view('invoices.email', [
            'invoice'         => $invoice,          // The invoice
            'templates'       => $templates,         // Available templates
            'primaryTemplate' => $primaryTemplate,   // Default template
            'shortcodes'      => $shortcodes,        // Shortcode values for preview
            'allShortcodes'   => config('hws.shortcodes'), // Shortcode reference list
        ]);
    }

    /**
     * Send an invoice notification email to the client.
     *
     * @param Request $request
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendEmail(Request $request, Invoice $invoice)
    {
        // Validate the email form
        $validated = $request->validate([
            'template_id' => 'required|exists:email_templates,id',  // Must be a valid template
            'to_email'    => 'required|email',                       // Valid email address
        ]);

        // Load the selected template
        $template = EmailTemplate::findOrFail($validated['template_id']);

        // Load the client and line items for shortcode building
        $invoice->load(['client', 'lineItems.employee']);

        // Build the work log HTML
        $workLogData = $invoice->lineItems->map(function ($item) {
            return [
                'date'          => $item->date->format('Y-m-d'),
                'description'   => $item->description,
                'time_minutes'  => $item->time_minutes,
                'employee_name' => $item->employee->name ?? '',
            ];
        })->toArray();
        // Generate HTML table from the work log data
        $workLogHtml = $this->generic->generateWorkLogHtml($workLogData);

        // Build shortcodes
        $shortcodes = $this->email->buildInvoiceShortcodes($invoice, $invoice->client, $workLogHtml);

        // Send the email using the template and shortcodes
        $result = $this->email->sendFromTemplate($template, $validated['to_email'], $shortcodes);

        // Determine the redirect message type
        $messageType = $result['success'] ? 'success' : 'error';

        // Redirect back with the result
        return redirect()
            ->route('invoices.index')
            ->with($messageType, $result['message']);
    }

    /**
     * Finalize and send an invoice via Stripe.
     * Finalizes the draft and triggers Stripe's email delivery.
     *
     * @param Invoice $invoice Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendViaStripe(Invoice $invoice)
    {
        // Check that we have a Stripe invoice ID
        if (empty($invoice->stripe_invoice_id)) {
            return redirect()
                ->route('invoices.index')
                ->with('error', 'No Stripe invoice ID associated with this invoice.');
        }

        // Step 1: Finalize the draft invoice on Stripe (using the correct Stripe account)
        $finalizeResult = $this->stripe->finalizeInvoice($invoice->stripe_invoice_id, $invoice->stripe_account_id);

        // Check if finalization succeeded
        if (!$finalizeResult['success']) {
            return redirect()
                ->route('invoices.index')
                ->with('error', 'Stripe finalization failed: ' . ($finalizeResult['error'] ?? 'Unknown'));
        }

        // Step 2: Send the finalized invoice via Stripe
        $sendResult = $this->stripe->sendInvoice($invoice->stripe_invoice_id, $invoice->stripe_account_id);

        // Check if sending succeeded
        if (!$sendResult['success']) {
            return redirect()
                ->route('invoices.index')
                ->with('error', 'Invoice finalized but send failed: ' . ($sendResult['error'] ?? 'Unknown'));
        }

        // Update the local invoice status to 'sent'
        $invoice->update([
            'status' => config('hws.invoice_statuses.sent'),
        ]);

        // Redirect with success message
        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice finalized and sent via Stripe.');
    }
}

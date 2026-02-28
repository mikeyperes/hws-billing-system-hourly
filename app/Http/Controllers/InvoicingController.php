<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientStripeLink;
use App\Models\ItemTemplate;
use App\Models\StripeAccount;
use App\Services\StripeService;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * InvoicingController -- Invoicing Center.
 * Create Stripe invoices by selecting a client, Stripe account, item template,
 * and amount. Auto-opens with the client's primary billing source.
 */
class InvoicingController extends Controller
{
    protected StripeService $stripe;
    protected GenericService $generic;

    public function __construct(StripeService $stripe, GenericService $generic)
    {
        $this->stripe = $stripe;
        $this->generic = $generic;
    }

    /**
     * Show the Invoicing Center page.
     */
    public function index(Request $request)
    {
        $clients = Client::active()->orderBy('name')->get();
        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();
        $templates = ItemTemplate::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        // Item template shortcodes
        $itemShortcodes = config('hws.item_shortcodes', []);

        // If a client is pre-selected, load their primary billing link
        $selectedClient = null;
        $primaryLink = null;
        if ($request->filled('client_id')) {
            $selectedClient = Client::find($request->client_id);
            if ($selectedClient) {
                $primaryLink = $selectedClient->primaryBillingLink();
            }
        }

        return view('invoicing.index', [
            'clients'        => $clients,
            'stripeAccounts' => $stripeAccounts,
            'templates'      => $templates,
            'itemShortcodes' => $itemShortcodes,
            'selectedClient' => $selectedClient,
            'primaryLink'    => $primaryLink,
        ]);
    }

    /**
     * Get the primary billing link for a client (AJAX).
     */
    public function getClientBilling(Client $client)
    {
        $primaryLink = $client->primaryBillingLink();
        $links = $client->stripeLinks()->with('stripeAccount')->get();

        return response()->json([
            'primary_link' => $primaryLink ? [
                'stripe_account_id'  => $primaryLink->stripe_account_id,
                'stripe_customer_id' => $primaryLink->stripe_customer_id,
                'account_name'       => $primaryLink->stripeAccount->name ?? '',
            ] : null,
            'all_links' => $links->map(fn ($l) => [
                'stripe_account_id'  => $l->stripe_account_id,
                'stripe_customer_id' => $l->stripe_customer_id,
                'account_name'       => $l->stripeAccount->name ?? '',
                'is_primary'         => $l->is_primary_billing,
                'is_hourly'          => $l->is_hourly_billing,
            ]),
        ]);
    }

    /**
     * Get a single item template (AJAX).
     */
    public function getTemplate(ItemTemplate $template)
    {
        return response()->json([
            'id'                   => $template->id,
            'name'                 => $template->name,
            'description_template' => $template->description_template,
            'default_amount_cents' => $template->default_amount_cents,
            'default_interval'     => $template->default_interval,
        ]);
    }

    /**
     * Create the invoice on Stripe.
     */
    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'stripe_account_id' => 'required|exists:stripe_accounts,id',
            'stripe_customer_id' => 'required|string|max:255',
            'description'      => 'required|string|max:1000',
            'amount'           => 'required|numeric|min:0.01',
            'interval'         => 'required|in:month,year,week,one_time',
            'due_days'         => 'nullable|integer|min:0|max:90',
            'memo'             => 'nullable|string|max:500',
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $amountCents = (int) round($validated['amount'] * 100);

        // Build line item description (append interval label)
        $intervalLabel = match ($validated['interval']) {
            'year'     => '(Annual)',
            'month'    => '(Monthly)',
            'week'     => '(Weekly)',
            'one_time' => '(One-Time)',
        };
        $description = $validated['description'];
        if (!str_contains(strtolower($description), strtolower(trim($intervalLabel, '()')))) {
            $description .= ' ' . $intervalLabel;
        }

        $result = $this->stripe->createInvoiceWithItems(
            customerId: $validated['stripe_customer_id'],
            lineItems: [['description' => $description, 'amount_cents' => $amountCents]],
            stripeAccountId: $validated['stripe_account_id'],
            dueDays: $validated['due_days'] ?? 30,
            memo: $validated['memo'] ?? null,
        );

        if (!$result['success']) {
            return redirect()->route('invoicing.index', ['client_id' => $client->id])
                ->with('error', 'Stripe error: ' . $result['error']);
        }

        $inv = $result['data'];
        return redirect()->route('invoicing.index', ['client_id' => $client->id])
            ->with('success', 'Invoice created: ' . $inv['id'])
            ->with('invoice_result', $inv);
    }

    // ─────────────────────────────────────────
    // ITEM TEMPLATE CRUD
    // ─────────────────────────────────────────

    public function templateIndex()
    {
        $templates = ItemTemplate::orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');
        $itemShortcodes = config('hws.item_shortcodes', []);

        return view('invoicing.templates', [
            'templates'      => $templates,
            'itemShortcodes' => $itemShortcodes,
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'category'             => 'required|string|max:100',
            'description_template' => 'required|string|max:1000',
            'default_amount_cents' => 'nullable|integer|min:0',
            'default_interval'     => 'required|in:month,year,week,one_time',
        ]);

        ItemTemplate::create($validated);
        return redirect()->route('invoicing.templates')
            ->with('success', 'Item template created.');
    }

    public function updateTemplate(Request $request, ItemTemplate $template)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'category'             => 'required|string|max:100',
            'description_template' => 'required|string|max:1000',
            'default_amount_cents' => 'nullable|integer|min:0',
            'default_interval'     => 'required|in:month,year,week,one_time',
            'is_active'            => 'boolean',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $template->update($validated);
        return redirect()->route('invoicing.templates')
            ->with('success', 'Template updated.');
    }

    public function destroyTemplate(ItemTemplate $template)
    {
        $template->delete();
        return redirect()->route('invoicing.templates')
            ->with('success', 'Template deleted.');
    }
}

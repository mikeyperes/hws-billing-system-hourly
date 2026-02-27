<?php

namespace App\Http\Controllers;

use App\Models\WhmServer;
use App\Models\HostingAccount;
use App\Models\HostingSubscription;
use App\Models\Client;
use App\Services\GenericService;
use Illuminate\Http\Request;

/**
 * HostingController — manages Hexa Cloud Services module.
 * Handles WHM servers, hosting accounts, and their Stripe subscriptions.
 */
class HostingController extends Controller
{
    protected GenericService $generic;

    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Validation rules for WHM server forms.
     * Single source of truth — used by both store and update.
     *
     * @param bool $isUpdate Whether this is an update (adds is_active rule)
     * @return array Laravel validation rules
     */
    private function serverRules(bool $isUpdate = false): array
    {
        $rules = [
            'name'        => 'required|string|max:255',
            'hostname'    => 'required|string|max:255',
            'port'        => 'integer|min:1|max:65535',
            'auth_type'   => 'required|in:root_password,api_token,access_hash',
            'username'    => 'required|string|max:255',
            'credentials' => 'nullable|string',
            'notes'       => 'nullable|string',
        ];

        if ($isUpdate) {
            $rules['is_active'] = 'boolean';
        }

        return $rules;
    }

    // ─────────────────────────────────────────
    // OVERVIEW
    // ─────────────────────────────────────────

    /**
     * Cloud Services dashboard — overview of all servers and accounts.
     * Uses GenericService::getCloudStats() — same data as the main dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $servers = WhmServer::withCount('hostingAccounts')->orderBy('name')->get();
        $cloudStats = $this->generic->getCloudStats();

        return view('hosting.index', [
            'servers'            => $servers,
            'totalAccounts'      => $cloudStats['total_accounts'],
            'activeAccounts'     => $cloudStats['active_accounts'],
            'totalSubscriptions' => $cloudStats['active_subscriptions'],
            'monthlyRevenue'     => $cloudStats['monthly_revenue'],
        ]);
    }

    // ─────────────────────────────────────────
    // WHM SERVERS
    // ─────────────────────────────────────────

    /**
     * List all WHM servers.
     *
     * @return \Illuminate\View\View
     */
    public function servers()
    {
        $servers = WhmServer::withCount('hostingAccounts')->orderBy('name')->get();

        return view('hosting.servers', [
            'servers' => $servers,
        ]);
    }

    /**
     * Show create server form.
     *
     * @return \Illuminate\View\View
     */
    public function createServer()
    {
        return view('hosting.server-create');
    }

    /**
     * Store a new WHM server.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeServer(Request $request)
    {
        $validated = $request->validate($this->serverRules());
        $server = WhmServer::create($validated);

        return redirect()
            ->route('hosting.servers')
            ->with('success', 'Server "' . $server->name . '" added successfully.');
    }

    /**
     * Show edit server form.
     *
     * @param WhmServer $server
     * @return \Illuminate\View\View
     */
    public function editServer(WhmServer $server)
    {
        return view('hosting.server-edit', [
            'server' => $server,
        ]);
    }

    /**
     * Update a WHM server.
     *
     * @param Request   $request
     * @param WhmServer $server
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateServer(Request $request, WhmServer $server)
    {
        $validated = $request->validate($this->serverRules(isUpdate: true));

        // Only update credentials if a new value was provided
        if (empty($validated['credentials'])) {
            unset($validated['credentials']);
        }

        $validated['is_active'] = $request->has('is_active');
        $server->update($validated);

        return redirect()
            ->route('hosting.servers')
            ->with('success', 'Server "' . $server->name . '" updated.');
    }

    // ─────────────────────────────────────────
    // HOSTING ACCOUNTS
    // ─────────────────────────────────────────

    /**
     * List all hosting accounts (across all servers).
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function accounts(Request $request)
    {
        $query = HostingAccount::with(['whmServer', 'client', 'activeSubscriptions']);

        // Optional filters
        if ($request->filled('server')) {
            $query->where('whm_server_id', $request->server);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $accounts = $query->orderBy('domain')->paginate(config('hws.per_page', 25));
        $servers = WhmServer::orderBy('name')->get();

        return view('hosting.accounts', [
            'accounts' => $accounts,
            'servers'  => $servers,
        ]);
    }

    /**
     * Show/edit a hosting account — assign client, manage subscriptions.
     *
     * @param HostingAccount $account
     * @return \Illuminate\View\View
     */
    public function editAccount(HostingAccount $account)
    {
        $account->load(['whmServer', 'client', 'subscriptions']);
        $clients = Client::orderBy('name')->get();

        return view('hosting.account-edit', [
            'account' => $account,
            'clients' => $clients,
        ]);
    }

    /**
     * Update a hosting account (assign client, add notes).
     *
     * @param Request        $request
     * @param HostingAccount $account
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateAccount(Request $request, HostingAccount $account)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'notes'     => 'nullable|string',
        ]);

        $account->update($validated);

        return redirect()
            ->route('hosting.account.edit', $account)
            ->with('success', 'Account updated.');
    }

    /**
     * Add a Stripe subscription to a hosting account.
     *
     * @param Request        $request
     * @param HostingAccount $account
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addSubscription(Request $request, HostingAccount $account)
    {
        $validated = $request->validate([
            'type'                   => 'required|string|max:255',
            'stripe_subscription_id' => 'nullable|string|max:255',
            'stripe_customer_id'     => 'nullable|string|max:255',
            'stripe_price_id'        => 'nullable|string|max:255',
            'status'                 => 'required|in:active,past_due,canceled,unpaid,trialing',
            'amount_cents'           => 'required|integer|min:0',
            'interval'               => 'required|in:month,year,week',
            'notes'                  => 'nullable|string',
        ]);

        $validated['hosting_account_id'] = $account->id;
        HostingSubscription::create($validated);

        return redirect()
            ->route('hosting.account.edit', $account)
            ->with('success', 'Subscription added.');
    }

    /**
     * Remove a subscription from a hosting account.
     *
     * @param HostingSubscription $subscription
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeSubscription(HostingSubscription $subscription)
    {
        $accountId = $subscription->hosting_account_id;
        $subscription->delete();

        return redirect()
            ->route('hosting.account.edit', $accountId)
            ->with('success', 'Subscription removed.');
    }
}

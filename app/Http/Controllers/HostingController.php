<?php

namespace App\Http\Controllers;

use App\Models\WhmServer;
use App\Models\HostingAccount;
use App\Models\HostingSubscription;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * HostingController — manages Hexa Cloud Services module.
 * Handles WHM servers, hosting accounts, and their Stripe subscriptions.
 * Foundation for tracking hosting infrastructure across multiple servers.
 */
class HostingController extends Controller
{
    /**
     * Cloud Services dashboard — overview of all servers and accounts.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get server summary stats
        $servers = WhmServer::withCount('hostingAccounts')->orderBy('name')->get();

        // Get total counts
        $totalAccounts = HostingAccount::count();
        $activeAccounts = HostingAccount::where('status', 'active')->count();
        $totalSubscriptions = HostingSubscription::where('status', 'active')->count();
        $monthlyRevenue = HostingSubscription::where('status', 'active')
            ->where('interval', 'month')
            ->sum('amount_cents');

        return view('hosting.index', [
            'servers'            => $servers,
            'totalAccounts'      => $totalAccounts,
            'activeAccounts'     => $activeAccounts,
            'totalSubscriptions' => $totalSubscriptions,
            'monthlyRevenue'     => $monthlyRevenue,
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
        // Validate the incoming form data
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'hostname'    => 'required|string|max:255',
            'port'        => 'integer|min:1|max:65535',
            'auth_type'   => 'required|in:root_password,api_token,access_hash',
            'username'    => 'required|string|max:255',
            'credentials' => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);

        // Create the server record
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
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'hostname'    => 'required|string|max:255',
            'port'        => 'integer|min:1|max:65535',
            'auth_type'   => 'required|in:root_password,api_token,access_hash',
            'username'    => 'required|string|max:255',
            'credentials' => 'nullable|string',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
        ]);

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

        // Optional server filter
        if ($request->filled('server')) {
            $query->where('whm_server_id', $request->server);
        }

        // Optional status filter
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

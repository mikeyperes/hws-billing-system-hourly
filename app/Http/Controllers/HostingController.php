<?php

namespace App\Http\Controllers;

use App\Models\WhmServer;
use App\Models\HostingAccount;
use App\Models\HostingSubscription;
use App\Models\StripeAccount;
use App\Models\ServerScript;
use App\Models\Client;
use App\Services\GenericService;
use App\Services\WhmService;
use App\Services\StripeService;
use Illuminate\Http\Request;

/**
 * HostingController — Hexa Cloud Services module.
 * WHM servers, hosting accounts, Stripe subscription mapping, server maintenance.
 */
class HostingController extends Controller
{
    protected GenericService $generic;
    protected WhmService $whm;
    protected StripeService $stripe;

    public function __construct(GenericService $generic, WhmService $whm, StripeService $stripe)
    {
        $this->generic = $generic;
        $this->whm = $whm;
        $this->stripe = $stripe;
    }

    /**
     * Validation rules for WHM server forms.
     * Single source of truth — used by both store and update.
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
        if ($isUpdate) $rules['is_active'] = 'boolean';
        return $rules;
    }

    // ─────────────────────────────────────────
    // OVERVIEW
    // ─────────────────────────────────────────

    /**
     * Cloud Services dashboard.
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

    public function servers()
    {
        $servers = WhmServer::withCount('hostingAccounts')->orderBy('name')->get();
        return view('hosting.servers', ['servers' => $servers]);
    }

    public function createServer()
    {
        return view('hosting.server-form', ['server' => null]);
    }

    public function storeServer(Request $request)
    {
        $validated = $request->validate($this->serverRules());
        $server = WhmServer::create($validated);
        return redirect()->route('hosting.servers')
            ->with('success', 'Server "' . $server->name . '" added.');
    }

    public function editServer(WhmServer $server)
    {
        return view('hosting.server-form', ['server' => $server]);
    }

    public function updateServer(Request $request, WhmServer $server)
    {
        $validated = $request->validate($this->serverRules(isUpdate: true));
        if (empty($validated['credentials'])) unset($validated['credentials']);
        $validated['is_active'] = $request->has('is_active');
        $server->update($validated);
        return redirect()->route('hosting.servers')
            ->with('success', 'Server "' . $server->name . '" updated.');
    }

    /**
     * Test WHM connection for a server.
     */
    public function testServer(WhmServer $server)
    {
        $result = $this->whm->testConnection($server);
        $type = $result['success'] ? 'success' : 'error';
        return redirect()->route('hosting.servers')
            ->with($type, $server->name . ': ' . $result['message']);
    }

    /**
     * Sync accounts from a WHM server.
     */
    public function syncServer(WhmServer $server)
    {
        $result = $this->whm->syncAccounts($server);
        $type = $result['success'] ? 'success' : 'error';
        return redirect()->route('hosting.accounts')
            ->with($type, $server->name . ': ' . $result['message']);
    }

    /**
     * Sync ALL active WHM servers.
     */
    public function syncAll()
    {
        $servers = WhmServer::where('is_active', true)->get();
        $messages = [];

        foreach ($servers as $server) {
            $result = $this->whm->syncAccounts($server);
            $messages[] = $server->name . ': ' . $result['message'];
        }

        return redirect()->route('hosting.accounts')
            ->with('success', implode(' | ', $messages));
    }

    // ─────────────────────────────────────────
    // HOSTING ACCOUNTS
    // ─────────────────────────────────────────

    public function accounts(Request $request)
    {
        $query = HostingAccount::with(['whmServer', 'client', 'activeSubscriptions']);

        if ($request->filled('server')) $query->where('whm_server_id', $request->server);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('owner', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('domain')->paginate(config('hws.per_page', 50));
        $servers = WhmServer::orderBy('name')->get();

        return view('hosting.accounts', [
            'accounts' => $accounts,
            'servers'  => $servers,
        ]);
    }

    public function editAccount(HostingAccount $account)
    {
        $account->load(['whmServer', 'client', 'subscriptions.stripeAccount']);
        $clients = Client::orderBy('name')->get();
        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();

        return view('hosting.account-edit', [
            'account'        => $account,
            'clients'        => $clients,
            'stripeAccounts' => $stripeAccounts,
        ]);
    }

    public function updateAccount(Request $request, HostingAccount $account)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'notes'     => 'nullable|string',
        ]);
        $account->update($validated);
        return redirect()->route('hosting.account.edit', $account)
            ->with('success', 'Account updated.');
    }

    // ─────────────────────────────────────────
    // SUBSCRIPTIONS — SMART ATTACHMENT
    // ─────────────────────────────────────────

    /**
     * Attach a Stripe subscription to a hosting account.
     * If a subscription ID is provided, fetch full details from Stripe.
     * Searches ALL Stripe accounts to find the subscription.
     */
    public function addSubscription(Request $request, HostingAccount $account)
    {
        $validated = $request->validate([
            'stripe_subscription_id' => 'nullable|string|max:255',
            'type'                   => 'required|string|max:255',
            'stripe_account_id'      => 'nullable|exists:stripe_accounts,id',
            'amount_cents'           => 'nullable|integer|min:0',
            'interval'               => 'nullable|in:month,year,week',
            'status'                 => 'nullable|in:active,past_due,canceled,unpaid,trialing',
            'notes'                  => 'nullable|string',
        ]);

        $subData = [
            'hosting_account_id' => $account->id,
            'type'               => $validated['type'],
            'notes'              => $validated['notes'] ?? null,
        ];

        // If a Stripe subscription ID was given, pull full details
        if (!empty($validated['stripe_subscription_id'])) {
            $stripeResult = $this->stripe->getSubscription(
                $validated['stripe_subscription_id'],
                $validated['stripe_account_id'] ?? null
            );

            if ($stripeResult['success']) {
                $data = $stripeResult['data'];
                $subData['stripe_subscription_id'] = $data['id'];
                $subData['stripe_account_id']      = $stripeResult['stripe_account_id'];
                $subData['stripe_customer_id']     = $data['customer_id'];
                $subData['stripe_customer_name']   = $data['customer_name'];
                $subData['stripe_customer_email']  = $data['customer_email'];
                $subData['stripe_price_id']        = $data['price_id'];
                $subData['stripe_product_name']    = $data['product_name'];
                $subData['stripe_description']     = $data['description'];
                $subData['status']                 = $data['status'];
                $subData['amount_cents']           = $data['amount_cents'];
                $subData['interval']               = $data['interval'];
                $subData['current_period_start']   = $data['current_period_start'];
                $subData['current_period_end']     = $data['current_period_end'];
                $subData['last_payment_at']        = $data['last_payment_at'];
                $subData['next_payment_at']        = $data['next_payment_at'];
                $subData['canceled_at']            = $data['canceled_at'];
            } else {
                return redirect()->route('hosting.account.edit', $account)
                    ->with('error', 'Stripe lookup failed: ' . $stripeResult['error']);
            }
        } else {
            // Manual entry without Stripe lookup
            $subData['stripe_account_id'] = $validated['stripe_account_id'] ?? null;
            $subData['amount_cents']      = $validated['amount_cents'] ?? 0;
            $subData['interval']          = $validated['interval'] ?? 'month';
            $subData['status']            = $validated['status'] ?? 'active';
        }

        HostingSubscription::create($subData);
        return redirect()->route('hosting.account.edit', $account)
            ->with('success', 'Subscription attached.');
    }

    /**
     * Remove a subscription from a hosting account.
     */
    public function removeSubscription(HostingSubscription $subscription)
    {
        $accountId = $subscription->hosting_account_id;
        $subscription->delete();
        return redirect()->route('hosting.account.edit', $accountId)
            ->with('success', 'Subscription removed.');
    }

    /**
     * Refresh subscription details from Stripe.
     */
    public function refreshSubscription(HostingSubscription $subscription)
    {
        if (empty($subscription->stripe_subscription_id)) {
            return redirect()->route('hosting.account.edit', $subscription->hosting_account_id)
                ->with('error', 'No Stripe subscription ID to refresh.');
        }

        $result = $this->stripe->getSubscription(
            $subscription->stripe_subscription_id,
            $subscription->stripe_account_id
        );

        if (!$result['success']) {
            return redirect()->route('hosting.account.edit', $subscription->hosting_account_id)
                ->with('error', 'Stripe refresh failed: ' . $result['error']);
        }

        $data = $result['data'];
        $subscription->update([
            'stripe_customer_name'  => $data['customer_name'],
            'stripe_customer_email' => $data['customer_email'],
            'stripe_product_name'   => $data['product_name'],
            'stripe_description'    => $data['description'],
            'status'                => $data['status'],
            'amount_cents'          => $data['amount_cents'],
            'interval'              => $data['interval'],
            'current_period_start'  => $data['current_period_start'],
            'current_period_end'    => $data['current_period_end'],
            'last_payment_at'       => $data['last_payment_at'],
            'next_payment_at'       => $data['next_payment_at'],
            'canceled_at'           => $data['canceled_at'],
        ]);

        return redirect()->route('hosting.account.edit', $subscription->hosting_account_id)
            ->with('success', 'Subscription refreshed from Stripe.');
    }

    // ─────────────────────────────────────────
    // SUBSCRIPTION MAPPING TOOL
    // ─────────────────────────────────────────

    /**
     * Show the subscription mapping tool page.
     */
    public function mappingTool()
    {
        $servers = WhmServer::orderBy('name')->get();
        $stripeAccounts = StripeAccount::active()->orderBy('name')->get();
        $hostingAccounts = HostingAccount::where('status', 'active')
            ->orderBy('domain')->get();
        $unmappedCount = $hostingAccounts->filter(fn ($a) =>
            !HostingSubscription::where('hosting_account_id', $a->id)->exists()
        )->count();

        return view('hosting.mapping-tool', [
            'servers'         => $servers,
            'stripeAccounts'  => $stripeAccounts,
            'hostingAccounts' => $hostingAccounts,
            'unmappedCount'   => $unmappedCount,
        ]);
    }

    /**
     * Run the subscription mapping scan.
     * Modes: 'selected' (chosen accounts), 'unmapped' (accounts without subs), 'all' (full scan).
     */
    public function runMapping(Request $request)
    {
        $validated = $request->validate([
            'mode'               => 'required|in:selected,unmapped,all',
            'account_ids'        => 'nullable|array',
            'account_ids.*'      => 'exists:hosting_accounts,id',
            'stripe_account_id'  => 'nullable|exists:stripe_accounts,id',
        ]);

        $mode = $validated['mode'];
        $stripeAccountId = $validated['stripe_account_id'] ?? null;

        // Determine which hosting accounts to scan
        $accountsQuery = HostingAccount::where('status', 'active');
        if ($mode === 'selected' && !empty($validated['account_ids'])) {
            $accountsQuery->whereIn('id', $validated['account_ids']);
        } elseif ($mode === 'unmapped') {
            $accountsQuery->doesntHave('subscriptions');
        }
        // 'all' = no extra filter
        $accounts = $accountsQuery->get();

        // Determine which Stripe accounts to search
        $stripeAccountIds = [];
        if ($stripeAccountId) {
            $stripeAccountIds[] = $stripeAccountId;
        } else {
            $stripeAccountIds = StripeAccount::active()->pluck('id')->toArray();
        }

        // Pull all active subscriptions from selected Stripe accounts
        $allStripeSubs = [];
        $scanLog = [];

        foreach ($stripeAccountIds as $saId) {
            $acctName = StripeAccount::find($saId)->name ?? 'Unknown';
            $scanLog[] = ['type' => 'info', 'message' => "Scanning Stripe account: {$acctName}..."];

            $result = $this->stripe->listActiveSubscriptions($saId);
            if ($result['success']) {
                $subs = $result['subscriptions'];
                $scanLog[] = ['type' => 'info', 'message' => "Found " . count($subs) . " active subscriptions in {$acctName}."];
                foreach ($subs as $sub) {
                    $sub['_stripe_account_id'] = $saId;
                    $sub['_stripe_account_name'] = $acctName;
                    $allStripeSubs[] = $sub;
                }
            } else {
                $scanLog[] = ['type' => 'error', 'message' => "Failed to scan {$acctName}: " . ($result['error'] ?? '')];
            }
        }

        // Already linked subscription IDs
        $linkedSubIds = HostingSubscription::whereNotNull('stripe_subscription_id')
            ->pluck('stripe_subscription_id')
            ->toArray();

        // Match subscriptions to hosting accounts by domain
        $matches = [];
        $unmatched = [];

        foreach ($allStripeSubs as $sub) {
            $subText = strtolower($sub['description'] . ' ' . $sub['product_name'] . ' ' . $sub['customer_name'] . ' ' . $sub['customer_email']);
            $isLinked = in_array($sub['id'], $linkedSubIds);

            $matchedAccount = null;
            $matchConfidence = 'none';

            foreach ($accounts as $account) {
                $domain = strtolower($account->domain);
                // Check if domain appears in the subscription description/product
                if (!empty($domain) && str_contains($subText, $domain)) {
                    $matchedAccount = $account;
                    $matchConfidence = 'high';
                    break;
                }
                // Check domain without TLD (e.g. "hexaweb" matches "hexawebsystems.com")
                $domainBase = explode('.', $domain)[0] ?? '';
                if (strlen($domainBase) > 3 && str_contains($subText, $domainBase)) {
                    $matchedAccount = $account;
                    $matchConfidence = 'medium';
                    // Don't break — keep looking for exact match
                }
            }

            $entry = [
                'subscription_id'     => $sub['id'],
                'stripe_account_id'   => $sub['_stripe_account_id'],
                'stripe_account_name' => $sub['_stripe_account_name'],
                'customer_name'       => $sub['customer_name'],
                'customer_email'      => $sub['customer_email'],
                'description'         => $sub['description'],
                'product_name'        => $sub['product_name'],
                'amount'              => '$' . number_format($sub['amount_cents'] / 100, 2),
                'interval'            => $sub['interval'],
                'status'              => $sub['status'],
                'already_linked'      => $isLinked,
            ];

            if ($matchedAccount) {
                $entry['matched_account_id'] = $matchedAccount->id;
                $entry['matched_domain']     = $matchedAccount->domain;
                $entry['confidence']         = $matchConfidence;
                $matches[] = $entry;
                $scanLog[] = [
                    'type'    => 'match',
                    'message' => "{$sub['id']}: \"{$sub['description']}\" → {$matchedAccount->domain} ({$matchConfidence})"
                        . ($isLinked ? ' [already linked]' : ''),
                ];
            } else {
                $entry['confidence'] = 'none';
                $unmatched[] = $entry;
                $scanLog[] = [
                    'type'    => 'unmatched',
                    'message' => "{$sub['id']}: \"{$sub['description']}\" — no domain match"
                        . ($isLinked ? ' [already linked]' : ''),
                ];
            }
        }

        $scanLog[] = [
            'type'    => 'summary',
            'message' => "Scan complete. " . count($matches) . " matched, " . count($unmatched) . " unmatched, "
                . count($allStripeSubs) . " total subscriptions scanned.",
        ];

        $servers = WhmServer::orderBy('name')->get();
        $stripeAccountsList = StripeAccount::active()->orderBy('name')->get();
        $hostingAccountsList = HostingAccount::where('status', 'active')->orderBy('domain')->get();

        return view('hosting.mapping-tool', [
            'servers'         => $servers,
            'stripeAccounts'  => $stripeAccountsList,
            'hostingAccounts' => $hostingAccountsList,
            'unmappedCount'   => HostingAccount::where('status', 'active')->doesntHave('subscriptions')->count(),
            'matches'         => $matches,
            'unmatched'       => $unmatched,
            'scanLog'         => $scanLog,
            'mode'            => $mode,
        ]);
    }

    /**
     * Quick-link a subscription from the mapping results.
     */
    public function quickLink(Request $request)
    {
        $validated = $request->validate([
            'subscription_id'  => 'required|string',
            'account_id'       => 'required|exists:hosting_accounts,id',
            'stripe_account_id' => 'nullable|integer',
            'type'             => 'required|string|max:255',
        ]);

        // Fetch the subscription details from Stripe
        $result = $this->stripe->getSubscription(
            $validated['subscription_id'],
            $validated['stripe_account_id'] ?? null
        );

        if (!$result['success']) {
            return redirect()->route('hosting.mapping-tool')
                ->with('error', 'Could not fetch subscription: ' . $result['error']);
        }

        $data = $result['data'];
        HostingSubscription::create([
            'hosting_account_id'    => $validated['account_id'],
            'type'                  => $validated['type'],
            'stripe_account_id'     => $result['stripe_account_id'],
            'stripe_subscription_id' => $data['id'],
            'stripe_customer_id'    => $data['customer_id'],
            'stripe_customer_name'  => $data['customer_name'],
            'stripe_customer_email' => $data['customer_email'],
            'stripe_price_id'       => $data['price_id'],
            'stripe_product_name'   => $data['product_name'],
            'stripe_description'    => $data['description'],
            'status'                => $data['status'],
            'amount_cents'          => $data['amount_cents'],
            'interval'              => $data['interval'],
            'current_period_start'  => $data['current_period_start'],
            'current_period_end'    => $data['current_period_end'],
            'last_payment_at'       => $data['last_payment_at'],
            'next_payment_at'       => $data['next_payment_at'],
            'canceled_at'           => $data['canceled_at'],
        ]);

        return redirect()->route('hosting.mapping-tool')
            ->with('success', 'Linked ' . $data['id'] . ' to account #' . $validated['account_id'] . '.');
    }

    // ─────────────────────────────────────────
    // SERVER MAINTENANCE
    // ─────────────────────────────────────────

    /**
     * Show the server maintenance page.
     */
    public function maintenance()
    {
        $servers = WhmServer::where('is_active', true)->orderBy('name')->get();
        $scripts = ServerScript::active()->orderBy('category')->orderBy('sort_order')->get();
        $scriptsByCategory = $scripts->groupBy('category');

        return view('hosting.maintenance', [
            'servers'          => $servers,
            'scriptsByCategory' => $scriptsByCategory,
        ]);
    }

    /**
     * Run a maintenance script on a selected server.
     */
    public function runScript(Request $request)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:whm_servers,id',
            'script_id' => 'required|exists:server_scripts,id',
        ]);

        $server = WhmServer::findOrFail($validated['server_id']);
        $script = ServerScript::findOrFail($validated['script_id']);

        $result = $this->whm->executeCommand($server, $script->command);

        $servers = WhmServer::where('is_active', true)->orderBy('name')->get();
        $scripts = ServerScript::active()->orderBy('category')->orderBy('sort_order')->get();
        $scriptsByCategory = $scripts->groupBy('category');

        return view('hosting.maintenance', [
            'servers'          => $servers,
            'scriptsByCategory' => $scriptsByCategory,
            'executedScript'   => $script,
            'executedServer'   => $server,
            'scriptResult'     => $result,
        ]);
    }

    /**
     * Show detailed server info page.
     */
    public function serverInfo(WhmServer $server)
    {
        $info = $this->whm->getServerInfo($server);

        return view('hosting.server-info', [
            'server' => $server,
            'info'   => $info['success'] ? $info['info'] : [],
            'error'  => $info['success'] ? null : ($info['error'] ?? 'Could not retrieve server info.'),
        ]);
    }
}

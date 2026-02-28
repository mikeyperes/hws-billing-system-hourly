{{-- Hosting account edit — assign client, manage subscriptions with Stripe detail --}}
@extends('layouts.app')
@section('title', 'Account: ' . $account->domain)
@section('header', $account->domain)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Account details --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Details</h2>
            <dl class="text-sm space-y-2 mb-4">
                <div><dt class="text-gray-500">Domain</dt><dd class="font-medium">{{ $account->domain }}</dd></div>
                <div><dt class="text-gray-500">Username</dt><dd class="font-mono text-xs">{{ $account->username }}</dd></div>
                <div><dt class="text-gray-500">Server</dt><dd>{{ $account->whmServer->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Owner</dt><dd>{{ $account->owner ?? 'root' }}</dd></div>
                <div><dt class="text-gray-500">Package</dt><dd>{{ $account->package ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $account->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($account->status) }}</span>
                    @if($account->suspend_reason) <span class="text-xs text-red-500 ml-1">{{ $account->suspend_reason }}</span> @endif
                </dd></div>
                <div><dt class="text-gray-500">IP</dt><dd class="font-mono text-xs">{{ $account->ip_address ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Disk</dt><dd>{{ $account->disk_used_mb }}M / {{ $account->disk_limit_mb ?: '∞' }}M</dd></div>
                @if($account->email)<div><dt class="text-gray-500">Email</dt><dd class="text-xs">{{ $account->email }}</dd></div>@endif
            </dl>

            <form method="POST" action="{{ route('hosting.account.update', $account) }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Assign Client</label>
                    <select name="client_id" id="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ $account->client_id == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $account->notes) }}</textarea>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Save</button>
            </form>
        </div>
    </div>

    {{-- Right: Subscriptions (2 cols) --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Existing subscriptions --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Stripe Subscriptions ({{ $account->subscriptions->count() }})</h2>

            @if($account->subscriptions->isEmpty())
                <p class="text-sm text-gray-400 italic mb-4">No subscriptions attached. Add one below or use the <a href="{{ route('hosting.mapping-tool') }}" class="text-blue-600 hover:underline">Mapping Tool</a>.</p>
            @else
                <div class="space-y-4 mb-6">
                    @foreach($account->subscriptions as $sub)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ ucfirst($sub->type) }}</span>
                                    <span class="text-lg font-bold text-gray-900">{{ $sub->formatted_amount }}<span class="text-sm text-gray-500 font-normal">/{{ $sub->interval }}</span></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($sub->status) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($sub->stripe_subscription_id)
                                        <form method="POST" action="{{ route('hosting.subscription.refresh', $sub) }}">@csrf
                                            <button class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200">Refresh</button>
                                        </form>
                                        @if($sub->stripe_dashboard_url)
                                            <a href="{{ $sub->stripe_dashboard_url }}" target="_blank" class="text-xs bg-purple-50 text-purple-700 px-2 py-1 rounded hover:bg-purple-100">Stripe ↗</a>
                                        @endif
                                    @endif
                                    <form method="POST" action="{{ route('hosting.subscription.remove', $sub) }}" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')
                                        <button class="text-xs text-red-500 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </div>

                            @if($sub->stripe_product_name || $sub->stripe_description)
                                <p class="text-sm text-gray-600">{{ $sub->stripe_product_name }}{{ $sub->stripe_description ? ' — ' . $sub->stripe_description : '' }}</p>
                            @endif

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 text-xs text-gray-500">
                                @if($sub->stripe_customer_name)<div>Customer: <span class="text-gray-700">{{ $sub->stripe_customer_name }}</span></div>@endif
                                @if($sub->stripe_customer_email)<div>Email: <span class="text-gray-700">{{ $sub->stripe_customer_email }}</span></div>@endif
                                @if($sub->last_payment_at)<div>Last paid: <span class="text-gray-700">{{ $sub->last_payment_at->format('M j, Y') }}</span></div>@endif
                                @if($sub->next_payment_at)<div>Next: <span class="text-gray-700">{{ $sub->next_payment_at->format('M j, Y') }}</span></div>@endif
                                @if($sub->current_period_start && $sub->current_period_end)
                                    <div>Period: <span class="text-gray-700">{{ $sub->current_period_start->format('M j') }} — {{ $sub->current_period_end->format('M j, Y') }}</span></div>
                                @endif
                                @if($sub->stripeAccount)<div>Account: <span class="text-gray-700">{{ $sub->stripeAccount->name }}</span></div>@endif
                            </div>

                            @if($sub->stripe_subscription_id)
                                <p class="text-xs text-gray-400 font-mono mt-2">{{ $sub->stripe_subscription_id }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Add subscription form --}}
            <div class="border-t border-gray-200 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Attach Subscription</h3>
                <form method="POST" action="{{ route('hosting.subscription.add', $account) }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Subscription ID</label>
                            <input type="text" name="stripe_subscription_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono" placeholder="sub_xxx (auto-populates details)">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Type</label>
                            <select name="type" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" required>
                                <option value="hosting">Hosting</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="domain">Domain</option>
                                <option value="ssl">SSL</option>
                                <option value="email_hosting">Email Hosting</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">Enter a Stripe subscription ID to auto-fetch all details. All connected Stripe accounts will be searched. Or leave blank for manual entry.</p>

                    {{-- Manual fallback fields (shown if no sub ID) --}}
                    <details class="mb-3">
                        <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Manual entry (no Stripe ID)</summary>
                        <div class="grid grid-cols-3 gap-3 mt-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Amount (cents)</label>
                                <input type="number" name="amount_cents" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="2999">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Interval</label>
                                <select name="interval" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    <option value="month">Monthly</option>
                                    <option value="year">Yearly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Status</label>
                                <select name="status" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    <option value="active">Active</option>
                                    <option value="past_due">Past Due</option>
                                    <option value="canceled">Canceled</option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Notes</label>
                        <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="Optional">
                    </div>

                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Attach Subscription</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

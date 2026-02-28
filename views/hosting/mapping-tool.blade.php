{{-- Subscription Mapping Tool — detect and link Stripe subscriptions to hosting accounts --}}
@extends('layouts.app')
@section('title', 'Subscription Mapping')
@section('header', 'Subscription Mapping Tool')

@section('content')
<div class="space-y-6">

    {{-- Controls --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Detect & Map Subscriptions</h2>
                <p class="text-sm text-gray-500 mt-1">Scans Stripe subscriptions and matches them to hosting accounts by domain name.</p>
            </div>
            <span class="text-sm text-gray-500">{{ $unmappedCount }} accounts without subscriptions</span>
        </div>

        <form method="POST" action="{{ route('hosting.mapping.run') }}">
            @csrf
            <div class="flex flex-wrap items-end gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Scan Mode</label>
                    <select name="mode" id="mapping-mode" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        onchange="document.getElementById('accounts-row').style.display = this.value === 'selected' ? '' : 'none'">
                        <option value="unmapped" {{ ($mode ?? '') === 'unmapped' ? 'selected' : '' }}>Unmapped accounts only ({{ $unmappedCount }})</option>
                        <option value="all" {{ ($mode ?? '') === 'all' ? 'selected' : '' }}>All active accounts</option>
                        <option value="selected" {{ ($mode ?? '') === 'selected' ? 'selected' : '' }}>Selected accounts</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Stripe Account</label>
                    <select name="stripe_account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All accounts</option>
                        @foreach($stripeAccounts as $sa)
                            <option value="{{ $sa->id }}">{{ $sa->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Run Scan</button>
            </div>

            {{-- Multi-select accounts (shown when mode = selected) --}}
            <div id="accounts-row" style="{{ ($mode ?? '') !== 'selected' ? 'display:none' : '' }}">
                <label class="block text-xs text-gray-500 mb-1">Select Accounts</label>
                <div class="border border-gray-300 rounded-lg p-2 max-h-48 overflow-y-auto bg-gray-50">
                    <div class="flex items-center gap-2 mb-2 px-1">
                        <button type="button" onclick="toggleAll(true)" class="text-xs text-blue-600 hover:underline">Select all</button>
                        <span class="text-xs text-gray-400">|</span>
                        <button type="button" onclick="toggleAll(false)" class="text-xs text-blue-600 hover:underline">Deselect all</button>
                    </div>
                    @foreach($hostingAccounts as $ha)
                        <label class="flex items-center gap-2 px-1 py-0.5 hover:bg-white rounded text-sm cursor-pointer">
                            <input type="checkbox" name="account_ids[]" value="{{ $ha->id }}" class="acct-cb rounded border-gray-300 text-blue-600">
                            <span class="font-medium">{{ $ha->domain }}</span>
                            <span class="text-xs text-gray-400">{{ $ha->username }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ $hostingAccounts->count() }} active accounts available.</p>
            </div>
        </form>
    </div>

    {{-- Scan Log --}}
    @if(!empty($scanLog))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Detection Log</h2>
            <div class="bg-gray-900 rounded-lg p-4 max-h-64 overflow-y-auto font-mono text-xs">
                @foreach($scanLog as $entry)
                    <div class="py-0.5 {{ $entry['type'] === 'error' ? 'text-red-400' : '' }}{{ $entry['type'] === 'match' ? 'text-green-400' : '' }}{{ $entry['type'] === 'unmatched' ? 'text-yellow-400' : '' }}{{ $entry['type'] === 'info' ? 'text-gray-400' : '' }}{{ $entry['type'] === 'summary' ? 'text-cyan-400 font-bold' : '' }}">
                        {{ $entry['message'] }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Matched Results --}}
    @if(!empty($matches))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Matched ({{ count($matches) }})</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-600">Subscription</th>
                            <th class="px-3 py-2 text-left text-gray-600">Description</th>
                            <th class="px-3 py-2 text-left text-gray-600">Customer</th>
                            <th class="px-3 py-2 text-left text-gray-600">Amount</th>
                            <th class="px-3 py-2 text-left text-gray-600">Matched Domain</th>
                            <th class="px-3 py-2 text-left text-gray-600">Confidence</th>
                            <th class="px-3 py-2 text-left text-gray-600">Stripe Acct</th>
                            <th class="px-3 py-2 text-left text-gray-600"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matches as $m)
                            <tr class="border-t border-gray-100 {{ $m['already_linked'] ? 'opacity-50' : '' }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($m['subscription_id'], 24) }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ \Illuminate\Support\Str::limit($m['description'] ?: $m['product_name'], 40) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $m['customer_name'] }}</td>
                                <td class="px-3 py-2 font-medium">{{ $m['amount'] }}/{{ $m['interval'] }}</td>
                                <td class="px-3 py-2 font-medium text-blue-700">{{ $m['matched_domain'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $m['confidence'] === 'high' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($m['confidence']) }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $m['stripe_account_name'] }}</td>
                                <td class="px-3 py-2">
                                    @if($m['already_linked'])
                                        <span class="text-xs text-gray-400">Linked</span>
                                    @else
                                        <form method="POST" action="{{ route('hosting.mapping.quick-link') }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="hidden" name="subscription_id" value="{{ $m['subscription_id'] }}">
                                            <input type="hidden" name="account_id" value="{{ $m['matched_account_id'] }}">
                                            <input type="hidden" name="stripe_account_id" value="{{ $m['stripe_account_id'] }}">
                                            <select name="type" class="border border-gray-300 rounded px-1 py-0.5 text-xs">
                                                <option value="hosting">Hosting</option>
                                                <option value="maintenance">Maint</option>
                                                <option value="domain">Domain</option>
                                                <option value="other">Other</option>
                                            </select>
                                            <button type="submit" class="text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700">Link</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Unmatched Results --}}
    @if(!empty($unmatched))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Unmatched ({{ count($unmatched) }})</h2>
            <p class="text-sm text-gray-500 mb-3">These Stripe subscriptions could not be matched to any hosting account domain.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-600">Subscription</th>
                            <th class="px-3 py-2 text-left text-gray-600">Description</th>
                            <th class="px-3 py-2 text-left text-gray-600">Customer</th>
                            <th class="px-3 py-2 text-left text-gray-600">Amount</th>
                            <th class="px-3 py-2 text-left text-gray-600">Stripe Acct</th>
                            <th class="px-3 py-2 text-left text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unmatched as $u)
                            <tr class="border-t border-gray-100 {{ $u['already_linked'] ? 'opacity-50' : '' }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($u['subscription_id'], 24) }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ \Illuminate\Support\Str::limit($u['description'] ?: $u['product_name'], 50) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $u['customer_name'] }}</td>
                                <td class="px-3 py-2 font-medium">{{ $u['amount'] }}/{{ $u['interval'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $u['stripe_account_name'] }}</td>
                                <td class="px-3 py-2">
                                    @if($u['already_linked'])
                                        <span class="text-xs text-gray-400">Already linked elsewhere</span>
                                    @else
                                        <span class="text-xs text-yellow-600">Unmatched</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.acct-cb').forEach(cb => cb.checked = checked);
}
</script>
@endsection

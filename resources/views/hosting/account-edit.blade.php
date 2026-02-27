{{-- Edit Hosting Account — assign client + manage Stripe subscriptions --}}
@extends('layouts.app')
@section('title', 'Edit Account')
@section('header', 'Account: ' . $account->domain)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Left: Account details --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Details</h2>
            <form method="POST" action="{{ route('hosting.account.update', $account) }}">
                @csrf
                @method('PUT')

                <dl class="text-sm space-y-2 mb-4">
                    <div><dt class="text-gray-500">Domain</dt><dd class="font-medium">{{ $account->domain }}</dd></div>
                    <div><dt class="text-gray-500">Username</dt><dd class="font-mono">{{ $account->username }}</dd></div>
                    <div><dt class="text-gray-500">Server</dt><dd>{{ $account->whmServer->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Package</dt><dd>{{ $account->package ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd>{{ ucfirst($account->status) }}</dd></div>
                    <div><dt class="text-gray-500">IP</dt><dd class="font-mono">{{ $account->ip_address ?? '—' }}</dd></div>
                </dl>

                <div class="mb-4">
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Assign Owner (Client)</label>
                    <select name="client_id" id="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ $account->client_id == $client->id ? 'selected' : '' }}>
                                {{ $client->name }} ({{ $client->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $account->notes) }}</textarea>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Save</button>
            </form>
        </div>
    </div>

    {{-- Right: Subscriptions --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Stripe Subscriptions</h2>

            @if($account->subscriptions->isEmpty())
                <p class="text-sm text-gray-400 italic mb-4">No subscriptions attached.</p>
            @else
                <div class="space-y-3 mb-4">
                    @foreach($account->subscriptions as $sub)
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-gray-900">{{ ucfirst($sub->type) }}</span>
                                    <span class="text-gray-500 text-xs ml-2">{{ $sub->formatted_amount }}/{{ $sub->interval }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ ucfirst($sub->status) }}
                                </span>
                            </div>
                            @if($sub->stripe_subscription_id)
                                <p class="text-xs text-gray-400 font-mono mt-1">{{ $sub->stripe_subscription_id }}</p>
                            @endif
                            <form method="POST" action="{{ route('hosting.subscription.remove', $sub) }}" class="mt-2" onsubmit="return confirm('Remove this subscription?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:underline">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Add subscription form --}}
            <h3 class="text-sm font-semibold text-gray-700 mb-2 mt-4 pt-4 border-t">Add Subscription</h3>
            <form method="POST" action="{{ route('hosting.subscription.add', $account) }}">
                @csrf
                <div class="grid grid-cols-2 gap-3 mb-3">
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
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <option value="active">Active</option>
                            <option value="past_due">Past Due</option>
                            <option value="canceled">Canceled</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Amount (cents)</label>
                        <input type="number" name="amount_cents" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="2999" required>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Interval</label>
                        <select name="interval" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <option value="month">Monthly</option>
                            <option value="year">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Stripe Subscription ID</label>
                    <input type="text" name="stripe_subscription_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="sub_xxx (optional)">
                </div>
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Stripe Customer ID</label>
                    <input type="text" name="stripe_customer_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="cus_xxx (optional)">
                </div>
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="Optional notes">
                </div>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Add Subscription</button>
            </form>
        </div>
    </div>
</div>
@endsection

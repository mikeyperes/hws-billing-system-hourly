{{-- Client edit: billing config, credit management, Stripe profiles --}}
@extends('layouts.app')
@section('title', 'Edit Client: ' . $client->name)
@section('header', 'Edit Client: ' . $client->name)

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Billing Configuration --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Billing Configuration</h2>

            <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm">
                <p class="text-gray-500">Email</p>
                <p class="text-gray-700">{{ $client->email ?? 'Not set' }}</p>
                @if($client->stripe_customer_id)
                    <p class="text-gray-500 mt-2">Legacy Stripe ID</p>
                    <p class="font-mono text-gray-400 text-xs">{{ $client->stripe_customer_id }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('clients.update', $client) }}">
                @csrf @method('PUT')

                <div class="mb-4">
                    <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                    <input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0"
                        value="{{ old('hourly_rate', $client->hourly_rate) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="billing_type" class="block text-sm font-medium text-gray-700 mb-1">Billing Type</label>
                    <select name="billing_type" id="billing_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Not Set —</option>
                        @foreach($billingTypes as $type)
                            <option value="{{ $type }}" {{ old('billing_type', $client->billing_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $client->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 mr-2">
                        <span class="text-sm text-gray-700">Active (included in billing scans)</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $client->notes) }}</textarea>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Save Changes</button>
            </form>
        </div>

        {{-- Credit Management --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Credit Balance</h2>
                <div class="text-center mb-4">
                    <p class="text-4xl font-bold {{ $client->isCreditLow() ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($client->credit_balance_hours, 2) }}
                    </p>
                    <p class="text-gray-500 text-sm">hours remaining</p>
                    @if($client->isCreditLow())
                        <p class="text-red-500 text-sm mt-2">Below threshold ({{ config('hws.credit_low_threshold_hours') }} hours)</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('clients.credits', $client) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adjust Hours</label>
                        <input type="number" name="adjustment" step="0.25" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g., 10 or -2.5">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" name="note" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Purchased 20-hour block">
                    </div>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 w-full">Adjust Credits</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Stripe Account Links --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Stripe Profiles</h2>
        <p class="text-sm text-gray-500 mb-4">Linked Stripe customer profiles across connected accounts. Set one as <span class="font-medium text-blue-700">Primary Billing</span> (used in Invoicing Center) and one as <span class="font-medium text-green-700">Hourly Billing</span> (used for hourly invoice creation).</p>

        @if($stripeLinks->isNotEmpty())
            <div class="space-y-4 mb-6">
                @foreach($stripeLinks as $link)
                    @php $detail = $stripeDetails[$link->id] ?? null; @endphp
                    <div class="border border-gray-200 rounded-lg p-4 {{ $link->is_primary_billing ? 'ring-2 ring-blue-200' : '' }}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ $link->stripeAccount->name ?? '(deleted)' }}</h3>
                                @if($link->is_primary_billing)
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Primary Billing</span>
                                @endif
                                @if($link->is_hourly_billing)
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Hourly Billing</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if($detail)
                                    <a href="{{ $detail['dashboard_url'] }}" target="_blank" class="text-xs bg-purple-50 text-purple-700 px-2 py-1 rounded hover:bg-purple-100">Edit in Stripe ↗</a>
                                @endif
                                @if(!$link->is_primary_billing)
                                    <form method="POST" action="{{ route('clients.stripe-link.set-primary', [$client, $link]) }}" class="inline">@csrf
                                        <button class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded hover:bg-blue-100">Set Primary</button>
                                    </form>
                                @endif
                                @if(!$link->is_hourly_billing)
                                    <form method="POST" action="{{ route('clients.stripe-link.set-hourly', [$client, $link]) }}" class="inline">@csrf
                                        <button class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded hover:bg-green-100">Set Hourly</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('clients.stripe-link.remove', [$client, $link]) }}" onsubmit="return confirm('Remove?')" class="inline">@csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </form>
                            </div>
                        </div>

                        <div class="text-sm font-mono text-gray-500 mb-2">{{ $link->stripe_customer_id }}</div>

                        @if($detail)
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-gray-500">Name</p>
                                    <p class="font-medium text-gray-800">{{ $detail['name'] ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="text-gray-700">{{ $detail['email'] ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Balance</p>
                                    <p class="font-medium {{ $detail['balance_cents'] < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                        ${{ number_format(abs($detail['balance_cents']) / 100, 2) }}
                                        {{ $detail['balance_cents'] < 0 ? '(credit)' : '' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Active Subs</p>
                                    <p class="font-medium text-gray-800">{{ $detail['active_subscriptions'] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">MRR</p>
                                    <p class="font-medium text-gray-800">${{ number_format($detail['mrr_cents'] / 100, 2) }}/mo</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Customer Since</p>
                                    <p class="text-gray-700">{{ $detail['created'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Payment Method</p>
                                    <p class="text-gray-700">{{ $detail['default_source'] ? '•••• ' . $detail['default_source'] : 'None' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Status</p>
                                    <p class="{{ $detail['delinquent'] ? 'text-red-600 font-medium' : 'text-green-700' }}">
                                        {{ $detail['delinquent'] ? 'Delinquent' : 'Good standing' }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Could not load Stripe details.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 italic mb-4">No Stripe accounts linked yet.</p>
        @endif

        {{-- Add link form --}}
        @if($stripeAccounts->isNotEmpty())
            <div class="p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Stripe Link</h3>
                <form method="POST" action="{{ route('clients.stripe-link.add', $client) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Account</label>
                            <select name="stripe_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                                @foreach($stripeAccounts as $acct)
                                    <option value="{{ $acct->id }}">{{ $acct->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Customer ID</label>
                            <input type="text" name="stripe_customer_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="cus_xxxxx" required>
                        </div>
                        <div class="flex items-end gap-3">
                            <label class="inline-flex items-center text-sm">
                                <input type="checkbox" name="is_hourly_billing" value="1" class="rounded border-gray-300 text-green-600">
                                <span class="ml-1 text-xs text-gray-600">Hourly</span>
                            </label>
                            <label class="inline-flex items-center text-sm">
                                <input type="checkbox" name="is_primary_billing" value="1" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-1 text-xs text-gray-600">Primary</span>
                            </label>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 w-full">Add</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

@endsection

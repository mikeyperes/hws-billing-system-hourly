{{-- Clients: edit page with billing config and credit adjustment --}}
@extends('layouts.app')

@section('title', 'Edit Client — ' . config('hws.app_name'))
@section('header', 'Edit Client: ' . $client->name)

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ═══ Client Details & Billing Config ═══ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Billing Configuration</h2>

            {{-- Stripe info (read-only) --}}
            <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm">
                <p class="text-gray-500">Stripe Customer ID</p>
                <p class="font-mono text-gray-700">{{ $client->stripe_customer_id }}</p>
                <p class="text-gray-500 mt-2">Email</p>
                <p class="text-gray-700">{{ $client->email ?? 'Not set' }}</p>
            </div>

            {{-- Edit form --}}
            <form method="POST" action="{{ route('clients.update', $client) }}">
                @csrf
                {{-- Laravel method spoofing for PUT --}}
                @method('PUT')

                {{-- Hourly rate --}}
                <div class="mb-4">
                    <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                    <input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0"
                        value="{{ old('hourly_rate', $client->hourly_rate) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('hourly_rate')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Billing type dropdown --}}
                <div class="mb-4">
                    <label for="billing_type" class="block text-sm font-medium text-gray-700 mb-1">Billing Type</label>
                    <select name="billing_type" id="billing_type"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        {{-- Empty/not set option --}}
                        <option value="">— Not Set —</option>
                        {{-- Loop through billing type options from Lists module --}}
                        @foreach($billingTypes as $type)
                            <option value="{{ $type }}" {{ old('billing_type', $client->billing_type) === $type ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Active toggle --}}
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active', $client->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 mr-2">
                        <span class="text-sm text-gray-700">Active (included in billing scans)</span>
                    </label>
                </div>

                {{-- Notes --}}
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $client->notes) }}</textarea>
                </div>

                {{-- Submit button --}}
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- ═══ Credit Management (only for hourly_credits) ═══ --}}
        <div class="space-y-6">
            {{-- Credit balance display --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Credit Balance</h2>

                {{-- Current balance --}}
                <div class="text-center mb-4">
                    <p class="text-4xl font-bold {{ $client->isCreditLow() ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($client->credit_balance_hours, 2) }}
                    </p>
                    <p class="text-gray-500 text-sm">hours remaining</p>

                    {{-- Low credit warning --}}
                    @if($client->isCreditLow())
                        <p class="text-red-500 text-sm mt-2">
                            ⚠️ Below threshold ({{ config('hws.credit_low_threshold_hours') }} hours)
                        </p>
                    @endif

                    {{-- Alert sent indicator --}}
                    @if($client->credit_alert_sent)
                        <p class="text-yellow-600 text-xs mt-1">Low credit alert has been sent</p>
                    @endif
                </div>

                {{-- Credit adjustment form --}}
                <form method="POST" action="{{ route('clients.credits', $client) }}">
                    @csrf
                    {{-- Adjustment amount --}}
                    <div class="mb-3">
                        <label for="adjustment" class="block text-sm font-medium text-gray-700 mb-1">
                            Adjust Hours (positive to add, negative to deduct)
                        </label>
                        <input type="number" name="adjustment" id="adjustment" step="0.25"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="e.g., 10 or -2.5">
                    </div>
                    {{-- Optional note --}}
                    <div class="mb-3">
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" name="note" id="note"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="e.g., Purchased 20-hour block">
                    </div>
                    {{-- Submit --}}
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 w-full">
                        Adjust Credits
                    </button>
                </form>
            </div>
        </div>
    </div>

@endsection

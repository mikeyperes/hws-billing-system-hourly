{{-- Invoice Generator — quick parameter-based output of Stripe IDs --}}
@extends('layouts.app')
@section('title', 'Invoice Generator')
@section('header', 'Invoice Generator')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Left: Input form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Generate Invoice Summary</h2>
        <p class="text-sm text-gray-500 mb-4">Select a client and enter details to get Stripe customer/subscription IDs for quick invoice creation.</p>

        <form method="POST" action="{{ route('invoice-generator.generate') }}">
            @csrf

            <div class="mb-4">
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select name="client_id" id="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Select a client...</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ (old('client_id', $summary['client_name'] ?? '') == $client->id) ? 'selected' : '' }}>
                            {{ $client->name }} — {{ $client->stripe_customer_id }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" name="description" id="description" value="{{ old('description') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="Web development — January 2026" required>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                    <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.01"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="500.00" required>
                </div>
                <div>
                    <label for="due_days" class="block text-sm font-medium text-gray-700 mb-1">Due In (days)</label>
                    <input type="number" name="due_days" id="due_days" value="{{ old('due_days', 30) }}" min="0" max="90"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                Generate Summary
            </button>
        </form>
    </div>

    {{-- Right: Output --}}
    <div>
        @if(isset($summary))
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Invoice Summary</h2>

                <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Client</span>
                        <span class="font-medium text-gray-900">{{ $summary['client_name'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Email</span>
                        <span class="text-gray-900">{{ $summary['client_email'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-gray-200 pt-3">
                        <span class="text-gray-500">Stripe Customer ID</span>
                        <span class="font-mono text-xs text-gray-900 bg-gray-200 px-2 py-0.5 rounded">{{ $summary['stripe_customer_id'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-gray-200 pt-3">
                        <span class="text-gray-500">Description</span>
                        <span class="text-gray-900">{{ $summary['description'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Amount</span>
                        <span class="font-bold text-gray-900">${{ $summary['amount'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Amount (cents)</span>
                        <span class="font-mono text-gray-900">{{ $summary['amount_cents'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Currency</span>
                        <span class="text-gray-900">{{ $summary['currency'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Due Date</span>
                        <span class="text-gray-900">{{ $summary['due_date'] }} ({{ $summary['due_days'] }} days)</span>
                    </div>
                </div>

                {{-- Copyable text block --}}
                <h3 class="text-sm font-semibold text-gray-700 mt-4 mb-2">Quick Copy</h3>
                <pre class="text-xs bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto">Client: {{ $summary['client_name'] }}
Email: {{ $summary['client_email'] }}
Stripe Customer: {{ $summary['stripe_customer_id'] }}
Description: {{ $summary['description'] }}
Amount: ${{ $summary['amount'] }} ({{ $summary['amount_cents'] }} cents)
Due: {{ $summary['due_date'] }}
Currency: {{ $summary['currency'] }}</pre>
                <button onclick="navigator.clipboard.writeText(document.querySelector('pre').innerText)"
                    class="mt-2 text-xs bg-gray-200 text-gray-600 px-3 py-1.5 rounded hover:bg-gray-300">
                    Copy to Clipboard
                </button>
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Output</h2>
                <p class="text-sm text-gray-400 italic">Fill out the form and click Generate to see the invoice summary.</p>
            </div>
        @endif

        {{-- Client quick reference --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Client Stripe IDs</h2>
            @if($clients->isEmpty())
                <p class="text-sm text-gray-400 italic">No clients with Stripe IDs found.</p>
            @else
                <div class="max-h-64 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-1.5 text-left text-gray-600">Client</th>
                                <th class="px-3 py-1.5 text-left text-gray-600">Stripe ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clients as $client)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-1.5 text-gray-900">{{ $client->name }}</td>
                                    <td class="px-3 py-1.5 font-mono text-gray-600">{{ $client->stripe_customer_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

{{-- Clients: list all clients with billing type, rate, and status --}}
@extends('layouts.app')

@section('title', 'Clients — ' . config('hws.app_name'))
@section('header', 'Clients')

@section('content')

    {{-- Top action bar --}}
    <div class="flex justify-between items-center mb-6">
        {{-- Client count --}}
        <p class="text-gray-500 text-sm">{{ $clients->total() }} client(s)</p>
        {{-- Import button --}}
        <a href="{{ route('clients.import') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            Import from Stripe
        </a>
    </div>

    {{-- Client table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            {{-- Table header --}}
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Name</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Email</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Billing Type</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Rate</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Credits</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Invoices</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Status</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Actions</th>
                </tr>
            </thead>
            {{-- Table body --}}
            <tbody>
                @forelse($clients as $client)
                    {{-- Individual client row --}}
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        {{-- Client name --}}
                        <td class="py-3 px-4 font-medium">{{ $client->name }}</td>
                        {{-- Client email --}}
                        <td class="py-3 px-4 text-gray-500">{{ $client->email ?? '—' }}</td>
                        {{-- Billing type badge --}}
                        <td class="py-3 px-4">
                            @if($client->billing_type)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">{{ $client->billing_type }}</span>
                            @else
                                <span class="text-xs text-gray-400">Not set</span>
                            @endif
                        </td>
                        {{-- Hourly rate --}}
                        <td class="py-3 px-4 text-right">${{ number_format($client->hourly_rate, 2) }}</td>
                        {{-- Credit balance (only relevant for hourly_credits) --}}
                        <td class="py-3 px-4 text-right">
                            @if($client->billing_type === 'hourly_credits')
                                {{-- Show balance with color coding --}}
                                <span class="{{ $client->isCreditLow() ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                    {{ number_format($client->credit_balance_hours, 2) }} hrs
                                </span>
                            @else
                                {{-- N/A for non-credit clients --}}
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        {{-- Invoice count --}}
                        <td class="py-3 px-4 text-center">{{ $client->invoices_count }}</td>
                        {{-- Active status badge --}}
                        <td class="py-3 px-4 text-center">
                            @if($client->is_active)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Active</span>
                            @else
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-1 rounded">Inactive</span>
                            @endif
                        </td>
                        {{-- Edit link --}}
                        <td class="py-3 px-4 text-right">
                            <a href="{{ route('clients.edit', $client) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    {{-- Empty state --}}
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">
                            No clients yet. <a href="{{ route('clients.import') }}" class="text-blue-600 hover:underline">Import from Stripe.</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination links --}}
    <div class="mt-4">
        {{ $clients->links() }}
    </div>

@endsection

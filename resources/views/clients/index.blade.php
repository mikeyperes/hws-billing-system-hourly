{{-- Clients: list all clients --}}
@extends('layouts.app')
@section('title', 'Clients')
@section('header', 'Clients')

@section('content')

    <div class="flex justify-between items-center mb-6">
        <p class="text-gray-500 text-sm">{{ $clients->total() }} client(s)</p>
        <a href="{{ route('clients.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Add Client</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Name</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Email</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Billing Type</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Rate</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Credits</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Stripe</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Invoices</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Status</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium">{{ $client->name }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $client->email ?? '—' }}</td>
                        <td class="py-3 px-4">
                            @if($client->billing_type)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">{{ $client->billing_type }}</span>
                            @else
                                <span class="text-xs text-gray-400">Not set</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">${{ number_format($client->hourly_rate, 2) }}</td>
                        <td class="py-3 px-4 text-right">
                            @if($client->billing_type === 'hourly_credits')
                                <span class="{{ $client->isCreditLow() ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                    {{ number_format($client->credit_balance_hours, 2) }} hrs
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            @php $linkCount = $client->stripeLinks->count(); @endphp
                            @if($linkCount > 0)
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">{{ $linkCount }} linked</span>
                            @else
                                <span class="text-xs text-gray-400">None</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">{{ $client->invoices_count }}</td>
                        <td class="py-3 px-4 text-center">
                            @if($client->is_active)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Active</span>
                            @else
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-1 rounded">Inactive</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            <a href="{{ route('clients.edit', $client) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-gray-500">
                            No clients yet. <a href="{{ route('clients.create') }}" class="text-blue-600 hover:underline">Add your first client.</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clients->links() }}
    </div>

@endsection

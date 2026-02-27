{{-- Invoices: list with status filter and action buttons --}}
@extends('layouts.app')

@section('title', 'Invoices — ' . config('hws.app_name'))
@section('header', 'Invoices')

@section('content')

    {{-- ═══ Filter Bar ═══ --}}
    <div class="flex justify-between items-center mb-6">
        {{-- Status filter tabs --}}
        <div class="flex gap-2">
            {{-- All invoices tab --}}
            <a href="{{ route('invoices.index') }}"
                class="px-3 py-1 rounded-lg text-sm {{ $currentStatus === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                All
            </a>
            {{-- Draft tab --}}
            <a href="{{ route('invoices.index', ['status' => 'draft']) }}"
                class="px-3 py-1 rounded-lg text-sm {{ $currentStatus === 'draft' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Draft
            </a>
            {{-- Sent tab --}}
            <a href="{{ route('invoices.index', ['status' => 'sent']) }}"
                class="px-3 py-1 rounded-lg text-sm {{ $currentStatus === 'sent' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Sent
            </a>
            {{-- Paid tab --}}
            <a href="{{ route('invoices.index', ['status' => 'paid']) }}"
                class="px-3 py-1 rounded-lg text-sm {{ $currentStatus === 'paid' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Paid
            </a>
        </div>
        {{-- Refresh all unpaid button --}}
        <form method="POST" action="{{ route('invoices.refresh-all') }}">
            @csrf
            <button type="submit" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">
                🔄 Refresh All Unpaid
            </button>
        </form>
    </div>

    {{-- ═══ Invoice Table ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Client</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Hours</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Amount</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Status</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Stripe ID</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Created</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        {{-- Client name --}}
                        <td class="py-3 px-4 font-medium">{{ $invoice->client->name ?? 'Deleted' }}</td>
                        {{-- Total hours --}}
                        <td class="py-3 px-4 text-right">{{ $invoice->total_hours }} hrs</td>
                        {{-- Total amount --}}
                        <td class="py-3 px-4 text-right font-medium">{{ $invoice->formatted_amount }}</td>
                        {{-- Status badge --}}
                        <td class="py-3 px-4 text-center">
                            @if($invoice->status === 'draft')
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">Draft</span>
                            @elseif($invoice->status === 'sent')
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Sent</span>
                            @elseif($invoice->status === 'paid')
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Paid</span>
                            @elseif($invoice->status === 'void')
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">Void</span>
                            @endif
                        </td>
                        {{-- Stripe invoice ID --}}
                        <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ Str::limit($invoice->stripe_invoice_id, 20) }}</td>
                        {{-- Created date --}}
                        <td class="py-3 px-4 text-gray-500">{{ $invoice->created_at->format('M j, Y') }}</td>
                        {{-- Action buttons --}}
                        <td class="py-3 px-4 text-right">
                            <div class="flex gap-1 justify-end" x-data="{ open: false }">
                                {{-- View line items --}}
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-800 text-xs px-2 py-1 bg-blue-50 rounded">
                                    View
                                </a>
                                {{-- Dropdown for more actions --}}
                                <div class="relative">
                                    <button @click="open = !open" class="text-gray-500 hover:text-gray-700 text-xs px-2 py-1 bg-gray-50 rounded">
                                        ⋯
                                    </button>
                                    {{-- Dropdown menu --}}
                                    <div x-show="open" @click.away="open = false"
                                        class="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 py-1">
                                        {{-- Mark as billed --}}
                                        @if($invoice->status === 'draft')
                                            <form method="POST" action="{{ route('invoices.mark-billed', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">✓ Mark as Billed</button>
                                            </form>
                                            {{-- Send via Stripe --}}
                                            <form method="POST" action="{{ route('invoices.send-stripe', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">📤 Finalize & Send (Stripe)</button>
                                            </form>
                                        @endif
                                        {{-- Send email --}}
                                        <a href="{{ route('invoices.email', $invoice) }}" class="block px-4 py-2 text-sm hover:bg-gray-50">✉️ Send Email</a>
                                        {{-- Refresh status --}}
                                        <form method="POST" action="{{ route('invoices.refresh', $invoice) }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">🔄 Refresh Status</button>
                                        </form>
                                        {{-- Reverse billing --}}
                                        @if($invoice->status !== 'paid')
                                            <form method="POST" action="{{ route('invoices.reverse', $invoice) }}"
                                                onsubmit="return confirm('Are you sure? This will reset sheet rows back to pending.')">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">↩️ Reverse Billing</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $invoices->links() }}</div>

@endsection

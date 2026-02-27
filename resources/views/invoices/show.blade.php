{{-- Invoices: detail view showing line items grouped by employee --}}
@extends('layouts.app')

@section('title', 'Invoice #' . $invoice->id . ' — ' . config('hws.app_name'))
@section('header', 'Invoice #' . $invoice->id . ' — ' . ($invoice->client->name ?? 'Deleted'))

@section('content')

    {{-- ═══ Invoice Summary ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Client</p>
                <p class="font-medium">{{ $invoice->client->name ?? 'Deleted' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Total Hours</p>
                <p class="font-medium">{{ $invoice->total_hours }} hrs</p>
            </div>
            <div>
                <p class="text-gray-500">Amount</p>
                <p class="font-medium text-lg">{{ $invoice->formatted_amount }}</p>
            </div>
            <div>
                <p class="text-gray-500">Status</p>
                <p class="font-medium">{{ ucfirst($invoice->status) }}</p>
            </div>
            <div>
                <p class="text-gray-500">Stripe Invoice</p>
                <p class="font-mono text-xs">{{ $invoice->stripe_invoice_id ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    {{-- ═══ Line Items by Employee ═══ --}}
    @foreach($itemsByEmployee as $employeeId => $items)
        {{-- Get employee name from the first item --}}
        @php $employeeName = $items->first()->employee->name ?? 'Unknown Employee'; @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            {{-- Employee header --}}
            <h3 class="font-semibold text-gray-800 mb-3">👤 {{ $employeeName }}</h3>

            {{-- Line items table --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">PK</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Date</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Description</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Domain</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Minutes</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @php $subtotalMinutes = 0; @endphp
                    @foreach($items as $item)
                        @php $subtotalMinutes += $item->time_minutes; @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-2 px-3 font-mono text-xs text-gray-400">{{ $item->primary_key }}</td>
                            <td class="py-2 px-3">{{ $item->date->format('M j, Y') }}</td>
                            <td class="py-2 px-3">{{ $item->description ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $item->domain ?? '—' }}</td>
                            <td class="py-2 px-3 text-right">{{ $item->time_minutes }}</td>
                            <td class="py-2 px-3 text-right">{{ $item->time_hours }}</td>
                        </tr>
                    @endforeach
                    {{-- Subtotal row --}}
                    <tr class="bg-gray-50 font-medium">
                        <td colspan="4" class="py-2 px-3">Subtotal ({{ $employeeName }})</td>
                        <td class="py-2 px-3 text-right">{{ $subtotalMinutes }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($subtotalMinutes / 60, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- ═══ Back link ═══ --}}
    <a href="{{ route('invoices.index') }}" class="text-blue-600 hover:underline text-sm">← Back to Invoices</a>

@endsection

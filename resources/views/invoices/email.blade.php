{{-- Invoices: email compose page with template selector and shortcode reference --}}
@extends('layouts.app')

@section('title', 'Send Invoice Email — ' . config('hws.app_name'))
@section('header', 'Send Email: Invoice #' . $invoice->id)

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══ Email Form (2 columns wide) ═══ --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('invoices.send-email', $invoice) }}">
                @csrf

                {{-- Recipient email --}}
                <div class="mb-4">
                    <label for="to_email" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="email" name="to_email" id="to_email"
                        value="{{ old('to_email', $invoice->client->email ?? '') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>

                {{-- Template selector --}}
                <div class="mb-4">
                    <label for="template_id" class="block text-sm font-medium text-gray-700 mb-1">Email Template</label>
                    <select name="template_id" id="template_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}"
                                {{ $primaryTemplate && $primaryTemplate->id === $template->id ? 'selected' : '' }}>
                                {{ $template->name }} {{ $template->is_primary ? '(Primary)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Invoice summary --}}
                <div class="mb-4 p-4 bg-gray-50 rounded-lg text-sm">
                    <p class="font-medium text-gray-700 mb-2">Invoice Summary</p>
                    <p>Client: {{ $invoice->client->name ?? 'N/A' }}</p>
                    <p>Hours: {{ $invoice->total_hours }} hrs</p>
                    <p>Amount: {{ $invoice->formatted_amount }}</p>
                    <p>Status: {{ ucfirst($invoice->status) }}</p>
                </div>

                {{-- Submit --}}
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700"
                    onclick="return confirm('Send this email to {{ $invoice->client->email ?? 'the recipient' }}?')">
                    Send Email
                </button>
            </form>
        </div>

        {{-- ═══ Shortcode Reference (1 column) ═══ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-3">Available Shortcodes</h3>
            <div class="space-y-2 text-sm">
                @foreach($shortcodes as $code => $description)
                    <div class="flex justify-between items-start">
                        <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $code }}</code>
                        <span class="text-gray-500 text-xs ml-2">{{ $description }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Current shortcode values --}}
            <h3 class="font-semibold text-gray-800 mt-6 mb-3">Current Values</h3>
            <div class="space-y-1 text-xs">
                @foreach($shortcodes as $code => $description)
                    <div>
                        <code class="text-gray-500">{{ $code }}</code> →
                        <span class="text-gray-700">{{ Str::limit($allShortcodes[$code] ?? ($shortcodes[$code] ?? ''), 40) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection

{{-- Invoicing Center — create Stripe invoices --}}
@extends('layouts.app')
@section('title', 'Invoicing Center')
@section('header', 'Invoicing Center')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Invoice Form (2 cols) --}}
    <div class="lg:col-span-2">

        {{-- Success: show invoice result --}}
        @if(session('invoice_result'))
            @php $inv = session('invoice_result'); @endphp
            <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
                <h3 class="font-semibold text-green-800 mb-2">Invoice Created</h3>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-green-600">Invoice ID</dt><dd class="font-mono text-green-900">{{ $inv['id'] }}</dd></div>
                    <div><dt class="text-green-600">Amount</dt><dd class="font-bold text-green-900">${{ number_format($inv['amount_due'] / 100, 2) }}</dd></div>
                    <div><dt class="text-green-600">Status</dt><dd>{{ ucfirst($inv['status']) }}</dd></div>
                    <div><dt class="text-green-600">Created</dt><dd>{{ $inv['created'] }}</dd></div>
                </dl>
                <div class="flex gap-2 mt-3">
                    @if($inv['dashboard_url'])
                        <a href="{{ $inv['dashboard_url'] }}" target="_blank" class="text-xs bg-purple-100 text-purple-700 px-3 py-1.5 rounded hover:bg-purple-200">Open in Stripe ↗</a>
                    @endif
                    @if($inv['hosted_url'])
                        <a href="{{ $inv['hosted_url'] }}" target="_blank" class="text-xs bg-blue-100 text-blue-700 px-3 py-1.5 rounded hover:bg-blue-200">Customer Invoice ↗</a>
                    @endif
                </div>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('invoicing.create') }}" id="invoice-form">
                @csrf

                {{-- Client --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                    <select name="client_id" id="client-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        <option value="">Select client...</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}"
                                {{ ($selectedClient && $selectedClient->id == $c->id) ? 'selected' : '' }}>
                                {{ $c->name }} {{ $c->email ? '(' . $c->email . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Stripe Account + Customer ID --}}
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stripe Account</label>
                        <select name="stripe_account_id" id="stripe-account-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="">Select...</option>
                            @foreach($stripeAccounts as $sa)
                                <option value="{{ $sa->id }}"
                                    {{ ($primaryLink && $primaryLink->stripe_account_id == $sa->id) ? 'selected' : '' }}>
                                    {{ $sa->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stripe Customer ID</label>
                        <input type="text" name="stripe_customer_id" id="stripe-customer-id"
                            value="{{ $primaryLink->stripe_customer_id ?? '' }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                            placeholder="cus_xxxxx" required>
                        <p class="text-xs text-gray-400 mt-1" id="billing-hint">
                            @if($primaryLink) Primary billing source auto-loaded @endif
                        </p>
                    </div>
                </div>

                {{-- Item Template --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Template</label>
                    <select id="template-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— No template (manual entry) —</option>
                        @foreach($templates as $category => $items)
                            <optgroup label="{{ ucfirst($category) }}">
                                @foreach($items as $t)
                                    <option value="{{ $t->id }}"
                                        data-desc="{{ $t->description_template }}"
                                        data-amount="{{ $t->default_amount_cents }}"
                                        data-interval="{{ $t->default_interval }}">
                                        {{ $t->name }} ({{ $t->formatted_amount }}/{{ $t->default_interval }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- Description (editable) --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Line Item Description</label>
                    <textarea name="description" id="description-field" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="e.g. Website Hosting — example.com (Annual)" required>{{ old('description') }}</textarea>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach(config('hws.item_shortcodes', []) as $code => $label)
                            <button type="button" class="shortcode-btn text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded hover:bg-blue-100 hover:text-blue-700"
                                data-code="{{ $code }}">{{ $code }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Amount + Interval + Due Days --}}
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                        <input type="number" name="amount" id="amount-field" step="0.01" min="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="299.00" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
                        <select name="interval" id="interval-field" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="year" selected>Yearly</option>
                            <option value="month">Monthly</option>
                            <option value="week">Weekly</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Due In (days)</label>
                        <input type="number" name="due_days" value="30" min="0" max="90"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Memo --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Memo (optional)</label>
                    <input type="text" name="memo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Internal note or customer-facing memo">
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm hover:bg-blue-700 font-medium"
                    onclick="return confirm('Create this invoice on Stripe?')">
                    Create Invoice
                </button>
            </form>
        </div>
    </div>

    {{-- Sidebar: Shortcodes + Quick Info --}}
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Item Shortcodes</h3>
            <p class="text-xs text-gray-500 mb-3">Click to insert into description. Resolved at creation time.</p>
            <div class="space-y-1.5">
                @foreach(config('hws.item_shortcodes', []) as $code => $label)
                    <div class="flex items-center justify-between text-sm">
                        <button type="button" class="shortcode-btn font-mono text-xs text-blue-600 hover:underline" data-code="{{ $code }}">{{ $code }}</button>
                        <span class="text-gray-500 text-xs">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Quick Links</h3>
            <div class="space-y-2 text-sm">
                <a href="{{ route('invoicing.templates') }}" class="text-blue-600 hover:underline block">Manage Item Templates</a>
                <a href="{{ route('emails.index') }}" class="text-blue-600 hover:underline block">Email Templates</a>
                <a href="{{ route('invoices.index') }}" class="text-blue-600 hover:underline block">All Invoices</a>
            </div>
        </div>
    </div>
</div>

<script>
// Template selection → populate description, amount, interval
document.getElementById('template-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    document.getElementById('description-field').value = opt.dataset.desc || '';
    const cents = parseInt(opt.dataset.amount) || 0;
    if (cents > 0) document.getElementById('amount-field').value = (cents / 100).toFixed(2);
    const interval = opt.dataset.interval || 'year';
    document.getElementById('interval-field').value = interval;
});

// Client change → fetch primary billing via AJAX
document.getElementById('client-select').addEventListener('change', function() {
    const clientId = this.value;
    if (!clientId) return;
    fetch('/invoicing/client-billing/' + clientId)
        .then(r => r.json())
        .then(data => {
            if (data.primary_link) {
                document.getElementById('stripe-account-select').value = data.primary_link.stripe_account_id;
                document.getElementById('stripe-customer-id').value = data.primary_link.stripe_customer_id;
                document.getElementById('billing-hint').textContent = 'Primary: ' + data.primary_link.account_name;
            } else if (data.all_links.length > 0) {
                const first = data.all_links[0];
                document.getElementById('stripe-account-select').value = first.stripe_account_id;
                document.getElementById('stripe-customer-id').value = first.stripe_customer_id;
                document.getElementById('billing-hint').textContent = 'No primary set — using first link';
            } else {
                document.getElementById('stripe-customer-id').value = '';
                document.getElementById('billing-hint').textContent = 'No Stripe links for this client';
            }
        });
});

// Click-to-add shortcodes
document.querySelectorAll('.shortcode-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const field = document.getElementById('description-field');
        const code = this.dataset.code;
        const pos = field.selectionStart || field.value.length;
        field.value = field.value.substring(0, pos) + code + field.value.substring(field.selectionEnd || pos);
        field.focus();
        field.selectionStart = field.selectionEnd = pos + code.length;
    });
});
</script>
@endsection

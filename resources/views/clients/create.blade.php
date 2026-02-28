{{-- Create a new client with Stripe profile attachment --}}
@extends('layouts.app')
@section('title', 'Add Client')
@section('header', 'Add Client')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Client Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g. Acme Corp" required autofocus>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="billing@example.com">
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                    <input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0"
                        value="{{ old('hourly_rate', config('hws.default_hourly_rate')) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="billing_type" class="block text-sm font-medium text-gray-700 mb-1">Billing Type</label>
                    <select name="billing_type" id="billing_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Not Set —</option>
                        @foreach($billingTypes as $type)
                            <option value="{{ $type }}" {{ old('billing_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            {{-- Stripe Profiles --}}
            <div class="border-t border-gray-200 pt-5 mb-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Stripe Profiles</h3>
                <p class="text-xs text-gray-500 mb-4">Attach one or more Stripe customer profiles. Set one as Primary Billing (used in Invoicing Center) and one as Hourly Billing (used for hourly invoices).</p>

                <div id="stripe-links-container">
                    <div class="stripe-link-row border border-gray-200 rounded-lg p-4 mb-3" data-index="0">
                        <div class="grid grid-cols-2 gap-3 mb-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Stripe Account</label>
                                <select name="stripe_links[0][stripe_account_id]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <option value="">— Select —</option>
                                    @foreach($stripeAccounts as $acct)
                                        <option value="{{ $acct->id }}">{{ $acct->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Stripe Customer ID</label>
                                <input type="text" name="stripe_links[0][stripe_customer_id]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="cus_xxxxx">
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center text-sm">
                                <input type="radio" name="primary_billing" value="0" class="text-blue-600">
                                <span class="ml-1 text-xs text-gray-600">Primary Billing</span>
                            </label>
                            <label class="inline-flex items-center text-sm">
                                <input type="radio" name="hourly_billing" value="0" class="text-green-600">
                                <span class="ml-1 text-xs text-gray-600">Hourly Billing</span>
                            </label>
                            <button type="button" class="remove-link text-xs text-red-500 hover:text-red-700 ml-auto hidden">Remove</button>
                        </div>
                    </div>
                </div>

                <button type="button" id="add-stripe-link" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200">+ Add Another Stripe Profile</button>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Create Client</button>
                <a href="{{ route('clients.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
let linkIndex = 1;
document.getElementById('add-stripe-link').addEventListener('click', function() {
    const container = document.getElementById('stripe-links-container');
    const row = document.querySelector('.stripe-link-row').cloneNode(true);
    row.dataset.index = linkIndex;
    // Update names
    row.querySelectorAll('select, input[type=text]').forEach(el => {
        el.name = el.name.replace('[0]', '[' + linkIndex + ']');
        el.value = '';
    });
    row.querySelectorAll('input[type=radio]').forEach(el => {
        el.value = linkIndex;
        el.checked = false;
    });
    // Show remove button
    row.querySelector('.remove-link').classList.remove('hidden');
    container.appendChild(row);
    linkIndex++;
    // Show all remove buttons if >1 rows
    document.querySelectorAll('.remove-link').forEach(btn => btn.classList.remove('hidden'));
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-link')) {
        e.target.closest('.stripe-link-row').remove();
        if (document.querySelectorAll('.stripe-link-row').length === 1) {
            document.querySelector('.remove-link').classList.add('hidden');
        }
    }
});
</script>
@endsection

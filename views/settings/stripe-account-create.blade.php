@extends('layouts.app')
@section('title', 'Add Stripe Account')
@section('header', 'Add Stripe Account')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('stripe-accounts.store') }}">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g. Hexa Web Services" required>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="secret_key" class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                <input type="password" name="secret_key" id="secret_key"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                    placeholder="sk_live_... or sk_test_..." required>
                <p class="text-xs text-gray-400 mt-1">Encrypted at rest. Never displayed in full after saving.</p>
                @error('secret_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="stripe_account_display" class="block text-sm font-medium text-gray-700 mb-1">Account Display ID <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="stripe_account_display" id="stripe_account_display" value="{{ old('stripe_account_display') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="acct_xxxxx">
                @error('stripe_account_display') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea name="notes" id="notes" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Add Account</button>
                <a href="{{ route('stripe-accounts.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

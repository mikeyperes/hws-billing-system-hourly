{{-- Stripe Account create/edit form --}}
@extends('layouts.app')
@section('title', $account ? 'Edit Stripe Account' : 'Add Stripe Account')
@section('header', $account ? 'Edit Stripe Account: ' . $account->name : 'Add Stripe Account')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">

        <form method="POST" action="{{ $account ? route('settings.stripe-accounts.update', $account) : route('settings.stripe-accounts.store') }}">
            @csrf
            @if($account) @method('PUT') @endif

            {{-- Friendly Name --}}
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Friendly Name</label>
                <input type="text" name="name" id="name"
                    value="{{ old('name', $account->name ?? '') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g. Hexa Web Services, Hexa Hosting"
                    required autofocus>
                <p class="text-xs text-gray-400 mt-1">This name appears in dropdowns and references throughout the system. Pick something distinctive and recognizable.</p>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Secret Key --}}
            <div class="mb-4">
                <label for="secret_key" class="block text-sm font-medium text-gray-700 mb-1">
                    Stripe Secret Key
                    @if($account) <span class="text-gray-400 font-normal">(leave blank to keep current)</span> @endif
                </label>
                <input type="password" name="secret_key" id="secret_key"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                    placeholder="sk_live_... or sk_test_..."
                    {{ $account ? '' : 'required' }}>
                @if($account)
                    <p class="text-xs text-gray-400 mt-1">Current: <code>{{ $account->masked_key }}</code></p>
                @endif
                @error('secret_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Display ID (optional) --}}
            <div class="mb-4">
                <label for="stripe_account_display" class="block text-sm font-medium text-gray-700 mb-1">Stripe Account ID <span class="text-gray-400 font-normal">(optional, for reference)</span></label>
                <input type="text" name="stripe_account_display" id="stripe_account_display"
                    value="{{ old('stripe_account_display', $account->stripe_account_display ?? '') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="acct_...">
                @error('stripe_account_display') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea name="notes" id="notes" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $account->notes ?? '') }}</textarea>
            </div>

            {{-- Default checkbox --}}
            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_default" value="1"
                        {{ old('is_default', $account->is_default ?? false) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700">Default account</span>
                </label>
                <p class="text-xs text-gray-400 mt-1">Used when no specific account is selected.</p>
            </div>

            {{-- Active checkbox (edit only) --}}
            @if($account)
                <div class="mb-6">
                    <label class="inline-flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            {{ $account->is_active ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    {{ $account ? 'Save Changes' : 'Add Account' }}
                </button>
                <a href="{{ route('settings.stripe-accounts.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">
                    Cancel
                </a>
            </div>
        </form>

    </div>
</div>
@endsection

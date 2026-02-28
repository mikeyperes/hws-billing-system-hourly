@extends('layouts.app')
@section('title', 'Edit Stripe Account')
@section('header', 'Edit Stripe Account: ' . $account->name)

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('stripe-accounts.update', $account) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $account->name) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="secret_key" class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                <input type="password" name="secret_key" id="secret_key"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                    placeholder="Leave blank to keep existing key">
                <p class="text-xs text-gray-400 mt-1">Current: {{ $account->masked_key }}</p>
                @error('secret_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="stripe_account_display" class="block text-sm font-medium text-gray-700 mb-1">Account Display ID</label>
                <input type="text" name="stripe_account_display" id="stripe_account_display"
                    value="{{ old('stripe_account_display', $account->stripe_account_display) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $account->notes) }}</textarea>
            </div>

            <div class="mb-4 flex items-center gap-6">
                <label class="inline-flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $account->is_active ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" {{ $account->is_default ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700">Default Account</span>
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Save Changes</button>
                <a href="{{ route('stripe-accounts.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

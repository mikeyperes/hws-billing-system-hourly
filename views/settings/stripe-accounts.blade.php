{{-- Stripe Accounts management page --}}
@extends('layouts.app')
@section('title', 'Stripe Accounts')
@section('header', 'Stripe Accounts')

@section('content')
<div class="max-w-4xl">

    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">Manage connected Stripe accounts across your business divisions.</p>
        <a href="{{ route('settings.stripe-accounts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Stripe Account
        </a>
    </div>

    @if($accounts->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No Stripe accounts configured yet.</p>
            <p class="text-sm text-gray-400 mt-1">Add your first Stripe account to start creating invoices.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($accounts as $account)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ $account->name }}</h3>
                                @if($account->is_default)
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Default</span>
                                @endif
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $account->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Key: <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">{{ $account->masked_key }}</code></p>
                            @if($account->stripe_account_display)
                                <p class="text-xs text-gray-400 mt-1">Account: {{ $account->stripe_account_display }}</p>
                            @endif
                            @if($account->notes)
                                <p class="text-xs text-gray-400 mt-1">{{ $account->notes }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('settings.stripe-accounts.test', $account) }}">
                                @csrf
                                <button type="submit" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200">Test</button>
                            </form>
                            <a href="{{ route('settings.stripe-accounts.edit', $account) }}" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200">Edit</a>
                            <form method="POST" action="{{ route('settings.stripe-accounts.destroy', $account) }}" onsubmit="return confirm('Delete this Stripe account?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs bg-red-50 text-red-600 px-3 py-1.5 rounded hover:bg-red-100">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('settings.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to Settings</a>
    </div>
</div>
@endsection

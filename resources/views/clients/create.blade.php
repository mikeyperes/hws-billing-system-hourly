{{-- Create a new client --}}
@extends('layouts.app')
@section('title', 'Add Client')
@section('header', 'Add Client')

@section('content')
<div class="max-w-lg">
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

            <div class="mb-4">
                <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                <input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0"
                    value="{{ old('hourly_rate', config('hws.default_hourly_rate')) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="mb-4">
                <label for="billing_type" class="block text-sm font-medium text-gray-700 mb-1">Billing Type</label>
                <select name="billing_type" id="billing_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Not Set —</option>
                    @foreach($billingTypes as $type)
                        <option value="{{ $type }}" {{ old('billing_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <p class="text-xs text-gray-400 mb-4">After creating, you can attach Stripe profiles from the client edit page.</p>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Create Client</button>
                <a href="{{ route('clients.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

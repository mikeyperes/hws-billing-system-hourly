{{-- Clients: Stripe import tool with debug panel --}}
@extends('layouts.app')

@section('title', 'Import Clients — ' . config('hws.app_name'))
@section('header', 'Import Clients from Stripe')

@section('content')

    {{-- Import form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        {{-- Instructions --}}
        <p class="text-gray-600 text-sm mb-4">
            Paste comma-separated Stripe Customer IDs below (e.g., <code class="bg-gray-100 px-1 rounded">cus_abc123, cus_def456</code>).
            Each customer will be retrieved from Stripe and created as a local client.
        </p>

        {{-- Import form --}}
        <form method="POST" action="{{ route('clients.import.process') }}">
            {{-- CSRF protection token --}}
            @csrf
            {{-- Textarea for Stripe Customer IDs --}}
            <div class="mb-4">
                <label for="stripe_ids" class="block text-sm font-medium text-gray-700 mb-1">Stripe Customer IDs</label>
                {{-- Textarea — preserves previous input on resubmit --}}
                <textarea
                    name="stripe_ids"
                    id="stripe_ids"
                    rows="4"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="cus_abc123, cus_def456, cus_ghi789"
                >{{ $originalInput ?? old('stripe_ids') }}</textarea>
                {{-- Validation error message --}}
                @error('stripe_ids')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            {{-- Submit button --}}
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                Import Customers
            </button>
        </form>
    </div>

    {{-- Debug panel — only shown after an import attempt --}}
    @if(isset($results) && !empty($results))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            {{-- Panel title --}}
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Import Results</h2>

            {{-- Results table --}}
            <div class="space-y-2">
                @foreach($results as $result)
                    {{-- Individual result row with color coding by status --}}
                    <div class="flex items-center p-3 rounded-lg text-sm
                        {{ $result['status'] === 'success' ? 'bg-green-50 border border-green-100' : '' }}
                        {{ $result['status'] === 'error' ? 'bg-red-50 border border-red-100' : '' }}
                        {{ $result['status'] === 'skipped' ? 'bg-yellow-50 border border-yellow-100' : '' }}
                    ">
                        {{-- Status icon --}}
                        <span class="mr-3">
                            @if($result['status'] === 'success')
                                ✅
                            @elseif($result['status'] === 'error')
                                ❌
                            @else
                                ⏭️
                            @endif
                        </span>
                        {{-- Stripe ID --}}
                        <code class="bg-gray-100 px-2 py-1 rounded text-xs mr-3">{{ $result['stripe_id'] }}</code>
                        {{-- Result message --}}
                        <span class="text-gray-700">{{ $result['message'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

@endsection

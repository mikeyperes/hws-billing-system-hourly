{{-- Email Templates: create new template --}}
@extends('layouts.app')

@section('title', 'New Email Template — ' . config('hws.app_name'))
@section('header', 'New Email Template')

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══ Form (2 columns) ═══ --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('emails.store') }}">
                @csrf

                {{-- Use case --}}
                <div class="mb-4">
                    <label for="use_case" class="block text-sm font-medium text-gray-700 mb-1">Use Case</label>
                    <input type="text" name="use_case" id="use_case" value="{{ old('use_case') }}"
                        list="use_case_options"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="e.g., invoice_notification" required>
                    {{-- Datalist for existing use cases --}}
                    <datalist id="use_case_options">
                        @foreach($useCases as $uc)
                            <option value="{{ $uc }}">
                        @endforeach
                    </datalist>
                </div>

                {{-- Template name --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>

                {{-- From fields --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="from_name" class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input type="text" name="from_name" id="from_name" value="{{ old('from_name', '{{company_name}}') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="from_email" class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                        <input type="text" name="from_email" id="from_email" value="{{ old('from_email') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Reply-to and CC --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="reply_to" class="block text-sm font-medium text-gray-700 mb-1">Reply To</label>
                        <input type="text" name="reply_to" id="reply_to" value="{{ old('reply_to') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="cc" class="block text-sm font-medium text-gray-700 mb-1">CC (comma-separated)</label>
                        <input type="text" name="cc" id="cc" value="{{ old('cc') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Subject --}}
                <div class="mb-4">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                {{-- Body --}}
                <div class="mb-4">
                    <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Body (HTML)</label>
                    <textarea name="body" id="body" rows="12"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">{{ old('body') }}</textarea>
                </div>

                {{-- Submit --}}
                <div class="flex gap-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Create Template</button>
                    <a href="{{ route('emails.index') }}" class="bg-gray-100 text-gray-600 px-6 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</a>
                </div>
            </form>
        </div>

        {{-- ═══ Shortcode Reference ═══ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-fit">
            <h3 class="font-semibold text-gray-800 mb-3">Shortcodes</h3>
            <div class="space-y-2 text-sm">
                @foreach($shortcodes as $code => $desc)
                    <div>
                        <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $code }}</code>
                        <span class="text-gray-500 text-xs block mt-1">{{ $desc }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection

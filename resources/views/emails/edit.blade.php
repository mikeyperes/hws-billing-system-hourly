{{-- Email Templates: edit form with test send --}}
@extends('layouts.app')

@section('title', 'Edit Template — ' . config('hws.app_name'))
@section('header', 'Edit Template: ' . $template->name)

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══ Edit Form (2 columns) ═══ --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('emails.update', $template) }}">
                @csrf
                @method('PUT')

                {{-- Use case --}}
                <div class="mb-4">
                    <label for="use_case" class="block text-sm font-medium text-gray-700 mb-1">Use Case</label>
                    <input type="text" name="use_case" id="use_case"
                        value="{{ old('use_case', $template->use_case) }}"
                        list="use_case_options"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <datalist id="use_case_options">
                        @foreach($useCases as $uc)
                            <option value="{{ $uc }}">
                        @endforeach
                    </datalist>
                </div>

                {{-- Template name --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="name" id="name"
                        value="{{ old('name', $template->name) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>

                {{-- From fields --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="from_name" class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input type="text" name="from_name" id="from_name"
                            value="{{ old('from_name', $template->from_name) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="from_email" class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                        <input type="text" name="from_email" id="from_email"
                            value="{{ old('from_email', $template->from_email) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Reply-to and CC --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="reply_to" class="block text-sm font-medium text-gray-700 mb-1">Reply To</label>
                        <input type="text" name="reply_to" id="reply_to"
                            value="{{ old('reply_to', $template->reply_to) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="cc" class="block text-sm font-medium text-gray-700 mb-1">CC</label>
                        <input type="text" name="cc" id="cc"
                            value="{{ old('cc', $template->cc) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Subject --}}
                <div class="mb-4">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" id="subject"
                        value="{{ old('subject', $template->subject) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                {{-- Body --}}
                <div class="mb-4">
                    <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Body (HTML)</label>
                    <textarea name="body" id="body" rows="12"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">{{ old('body', $template->body) }}</textarea>
                </div>

                {{-- Active toggle --}}
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 mr-2">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- ═══ Sidebar: Test Send + Shortcodes ═══ --}}
        <div class="space-y-6">

            {{-- Test email send --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Test Send</h3>
                <form method="POST" action="{{ route('emails.test', $template) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="test_email" class="block text-sm font-medium text-gray-700 mb-1">Send test to:</label>
                        <input type="email" name="test_email" id="test_email"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="you@example.com" required>
                    </div>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 w-full">
                        Send Test Email
                    </button>
                    <p class="text-xs text-gray-400 mt-2">Uses sample shortcode data.</p>
                </form>
            </div>

            {{-- Shortcode reference --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
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
    </div>

@endsection

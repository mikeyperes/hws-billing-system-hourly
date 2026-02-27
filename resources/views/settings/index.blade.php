{{-- Settings: grouped settings form with test email and server info --}}
@extends('layouts.app')

@section('title', 'Settings — ' . config('hws.app_name'))
@section('header', 'Settings')

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══ Settings Form (2 columns) ═══ --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('settings.update') }}">
                @csrf

                {{-- Loop through each settings group --}}
                @foreach($groupedSettings as $group => $settings)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
                        {{-- Group header --}}
                        <h2 class="font-semibold text-gray-800 mb-4 capitalize">{{ $group }} Settings</h2>

                        {{-- Individual settings within the group --}}
                        @foreach($settings as $setting)
                            <div class="mb-4">
                                {{-- Setting label --}}
                                <label for="settings_{{ $setting->key }}" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $setting->label ?? $setting->key }}
                                </label>

                                {{-- Render different input types based on setting type --}}
                                @if($setting->type === 'textarea')
                                    <textarea name="settings[{{ $setting->key }}]" id="settings_{{ $setting->key }}" rows="3"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $setting->value }}</textarea>
                                @elseif($setting->type === 'password')
                                    <input type="password" name="settings[{{ $setting->key }}]" id="settings_{{ $setting->key }}"
                                        value="{{ $setting->value }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @elseif($setting->type === 'boolean')
                                    <select name="settings[{{ $setting->key }}]" id="settings_{{ $setting->key }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="1" {{ $setting->value == '1' ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ $setting->value == '0' ? 'selected' : '' }}>No</option>
                                    </select>
                                @else
                                    <input type="text" name="settings[{{ $setting->key }}]" id="settings_{{ $setting->key }}"
                                        value="{{ $setting->value }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach

                {{-- Save button --}}
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Save All Settings
                </button>
            </form>
        </div>

        {{-- ═══ Sidebar: Test Email + Server Info ═══ --}}
        <div class="space-y-6">

            {{-- SMTP Test --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Test SMTP</h3>
                <form method="POST" action="{{ route('settings.test-email') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="test_email" class="block text-sm font-medium text-gray-700 mb-1">Send test email to:</label>
                        <input type="email" name="test_email" id="test_email"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="you@example.com" required>
                    </div>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 w-full">
                        Send Test Email
                    </button>
                </form>
            </div>

            {{-- Server Information --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Server Info</h3>
                <div class="space-y-2 text-sm">
                    @foreach($serverInfo as $label => $value)
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ Str::title(str_replace('_', ' ', $label)) }}</span>
                            <span class="font-medium text-gray-700">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

@endsection

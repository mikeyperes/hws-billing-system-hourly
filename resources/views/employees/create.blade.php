{{-- Employees: create new employee form --}}
@extends('layouts.app')

@section('title', 'Add Employee — ' . config('hws.app_name'))
@section('header', 'Add Employee')

@section('content')

    <div class="max-w-lg">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            {{-- Create employee form --}}
            <form method="POST" action="{{ route('employees.store') }}">
                @csrf

                {{-- Employee name --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Employee Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Google Sheet URL or ID --}}
                <div class="mb-4">
                    <label for="google_sheet_id" class="block text-sm font-medium text-gray-700 mb-1">Google Sheet URL or ID</label>
                    <input type="text" name="google_sheet_id" id="google_sheet_id" value="{{ old('google_sheet_id') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="https://docs.google.com/spreadsheets/d/abc123/edit" required>
                    {{-- Help text --}}
                    <p class="text-xs text-gray-400 mt-1">
                        The sheet must be shared with the service account ({{ config('hws.google.service_account_email') }}) as Editor.
                    </p>
                    @error('google_sheet_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div class="flex gap-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                        Create Employee
                    </button>
                    <a href="{{ route('employees.index') }}" class="bg-gray-100 text-gray-600 px-6 py-2 rounded-lg text-sm hover:bg-gray-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

@endsection

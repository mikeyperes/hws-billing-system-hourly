{{-- Employees: list all employees --}}
@extends('layouts.app')

@section('title', 'Employees — ' . config('hws.app_name'))
@section('header', 'Employees')

@section('content')

    {{-- Top action bar --}}
    <div class="flex justify-between items-center mb-6">
        <p class="text-gray-500 text-sm">{{ $employees->total() }} employee(s)</p>
        <a href="{{ route('employees.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Add Employee</a>
    </div>

    {{-- Employee table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Name</th>
                    <th class="text-left py-3 px-4 text-gray-500 font-medium">Google Sheet</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Scan Start PK</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Last Billing PK</th>
                    <th class="text-center py-3 px-4 text-gray-500 font-medium">Status</th>
                    <th class="text-right py-3 px-4 text-gray-500 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium">{{ $employee->name }}</td>
                        {{-- Sheet ID as a link to Google Sheets --}}
                        <td class="py-3 px-4">
                            <a href="{{ $employee->sheet_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-mono">
                                {{ Str::limit($employee->google_sheet_id, 20) }}
                            </a>
                        </td>
                        <td class="py-3 px-4 text-right font-mono">{{ $employee->scan_start_primary_key }}</td>
                        <td class="py-3 px-4 text-right font-mono">{{ $employee->last_billing_primary_key }}</td>
                        <td class="py-3 px-4 text-center">
                            @if($employee->is_active)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Active</span>
                            @else
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-1 rounded">Inactive</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            <a href="{{ route('employees.edit', $employee) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">
                            No employees yet. <a href="{{ route('employees.create') }}" class="text-blue-600 hover:underline">Add one.</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $employees->links() }}</div>

@endsection

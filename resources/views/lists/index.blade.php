{{-- Lists: management page for dynamic lookup values --}}
@extends('layouts.app')

@section('title', 'Lists — ' . config('hws.app_name'))
@section('header', 'Lists')

@section('content')

    {{-- ═══ Add New Item Form ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">Add List Item</h2>
        <form method="POST" action="{{ route('lists.store') }}" class="flex gap-4 items-end">
            @csrf
            {{-- List key (category) --}}
            <div class="flex-1">
                <label for="list_key" class="block text-sm font-medium text-gray-700 mb-1">List Key</label>
                <input type="text" name="list_key" id="list_key" value="{{ old('list_key') }}"
                    list="list_key_options"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g., customer_billing_type" required>
                {{-- Datalist for existing keys --}}
                <datalist id="list_key_options">
                    @foreach($lists as $key => $items)
                        <option value="{{ $key }}">
                    @endforeach
                </datalist>
            </div>
            {{-- List value --}}
            <div class="flex-1">
                <label for="list_value" class="block text-sm font-medium text-gray-700 mb-1">Value</label>
                <input type="text" name="list_value" id="list_value" value="{{ old('list_value') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g., hourly_open" required>
            </div>
            {{-- Submit --}}
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">
                + Add
            </button>
        </form>
    </div>

    {{-- ═══ Existing Lists ═══ --}}
    @forelse($lists as $listKey => $items)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            {{-- List key header --}}
            <h2 class="font-semibold text-gray-800 mb-3">
                <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ $listKey }}</code>
                <span class="text-gray-400 text-sm font-normal ml-2">{{ $items->count() }} item(s)</span>
            </h2>

            {{-- Items --}}
            <div class="space-y-2">
                @foreach($items as $item)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        {{-- Value and status --}}
                        <div class="flex items-center gap-2">
                            <span class="text-gray-700 {{ !$item->is_active ? 'line-through text-gray-400' : '' }}">
                                {{ $item->list_value }}
                            </span>
                            @if(!$item->is_active)
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded">Inactive</span>
                            @endif
                            <span class="text-xs text-gray-400">sort: {{ $item->sort_order }}</span>
                        </div>
                        {{-- Actions --}}
                        <div class="flex gap-2">
                            {{-- Toggle active/inactive --}}
                            <form method="POST" action="{{ route('lists.toggle', $item) }}">
                                @csrf
                                <button type="submit" class="text-xs {{ $item->is_active ? 'text-yellow-600 bg-yellow-50' : 'text-green-600 bg-green-50' }} px-2 py-1 rounded hover:opacity-80">
                                    {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            {{-- Delete --}}
                            <form method="POST" action="{{ route('lists.destroy', $item) }}"
                                onsubmit="return confirm('Delete this item permanently?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 bg-red-50 px-2 py-1 rounded hover:opacity-80">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
            No lists configured. Add one using the form above.
        </div>
    @endforelse

@endsection

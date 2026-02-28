{{-- Item Template Management --}}
@extends('layouts.app')
@section('title', 'Item Templates')
@section('header', 'Item Templates')

@section('content')
<div class="space-y-6">

    {{-- Shortcodes reference --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Available Shortcodes</h2>
        <div class="flex flex-wrap gap-3">
            @foreach($itemShortcodes as $code => $label)
                <span class="text-xs"><code class="bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded font-mono">{{ $code }}</code> <span class="text-gray-500">{{ $label }}</span></span>
            @endforeach
        </div>
    </div>

    {{-- Existing templates by category --}}
    @foreach($templates as $category => $items)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ ucfirst($category) }}</h2>
            <div class="space-y-3">
                @foreach($items as $t)
                    <form method="POST" action="{{ route('invoicing.template.update', $t) }}" class="border border-gray-100 rounded-lg p-4">
                        @csrf @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Name</label>
                                <input type="text" name="name" value="{{ $t->name }}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Category</label>
                                <input type="text" name="category" value="{{ $t->category }}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Default Amount (cents)</label>
                                <input type="number" name="default_amount_cents" value="{{ $t->default_amount_cents }}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Interval</label>
                                <select name="default_interval" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    <option value="year" {{ $t->default_interval === 'year' ? 'selected' : '' }}>Yearly</option>
                                    <option value="month" {{ $t->default_interval === 'month' ? 'selected' : '' }}>Monthly</option>
                                    <option value="week" {{ $t->default_interval === 'week' ? 'selected' : '' }}>Weekly</option>
                                    <option value="one_time" {{ $t->default_interval === 'one_time' ? 'selected' : '' }}>One-Time</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs text-gray-500 mb-1">Description Template</label>
                            <textarea name="description_template" rows="2" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono" required>{{ $t->description_template }}</textarea>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <label class="inline-flex items-center text-sm">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" {{ $t->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="ml-1 text-xs text-gray-600">Active</span>
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">Save</button>
                                <button type="button" onclick="if(confirm('Delete?')) document.getElementById('del-{{ $t->id }}').submit()" class="text-xs text-red-500 hover:underline">Delete</button>
                            </div>
                        </div>
                    </form>
                    <form id="del-{{ $t->id }}" method="POST" action="{{ route('invoicing.template.destroy', $t) }}" class="hidden">@csrf @method('DELETE')</form>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($templates->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No item templates yet. Create one below.</p>
        </div>
    @endif

    {{-- Add new template --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Item Template</h2>
        <form method="POST" action="{{ route('invoicing.template.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="Annual Hosting" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Category</label>
                    <input type="text" name="category" list="category-list" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="hosting" required>
                    <datalist id="category-list">
                        @foreach($templates->keys() as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                        <option value="hosting">
                        <option value="maintenance">
                        <option value="domain">
                        <option value="custom">
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Default Amount (cents)</label>
                    <input type="number" name="default_amount_cents" value="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Interval</label>
                    <select name="default_interval" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        <option value="year" selected>Yearly</option>
                        <option value="month">Monthly</option>
                        <option value="one_time">One-Time</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Description Template</label>
                <textarea name="description_template" id="new-desc" rows="2" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono"
                    placeholder="Website Hosting — {{client_name}} ({{year}})" required></textarea>
                <div class="flex flex-wrap gap-1 mt-1">
                    @foreach($itemShortcodes as $code => $label)
                        <button type="button" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded hover:bg-blue-100 hover:text-blue-700"
                            onclick="insertCode('new-desc', '{{ $code }}')">{{ $code }}</button>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Add Template</button>
        </form>
    </div>
</div>

<script>
function insertCode(fieldId, code) {
    const f = document.getElementById(fieldId);
    const pos = f.selectionStart || f.value.length;
    f.value = f.value.substring(0, pos) + code + f.value.substring(f.selectionEnd || pos);
    f.focus();
    f.selectionStart = f.selectionEnd = pos + code.length;
}
</script>
@endsection

{{-- Shared email template form partial --}}
{{-- Expects: $template (null for create), $useCases, $shortcodes --}}

@php $isEdit = !is_null($template); @endphp

{{-- Shortcodes reference at top --}}
<div class="bg-gray-50 rounded-lg p-4 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-2">Available Shortcodes <span class="font-normal text-gray-400">— click to insert into focused field</span></h3>
    <div class="flex flex-wrap gap-1">
        @foreach($shortcodes as $code => $label)
            <button type="button" class="shortcode-insert text-xs bg-white border border-gray-200 text-gray-700 px-2 py-1 rounded hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 font-mono"
                data-code="{{ $code }}" title="{{ $label }}">{{ $code }}</button>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Use Case</label>
        <input type="text" name="use_case" list="usecase-list"
            value="{{ old('use_case', $isEdit ? $template->use_case : '') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required placeholder="e.g. invoice_notification">
        <datalist id="usecase-list">
            @foreach($useCases as $uc)
                <option value="{{ $uc }}">
            @endforeach
        </datalist>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
        <input type="text" name="name" value="{{ old('name', $isEdit ? $template->name : '') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required placeholder="e.g. Formal Invoice Notice">
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
        <input type="text" name="from_name" id="field-from-name" value="{{ old('from_name', $isEdit ? $template->from_name : config('hws.company_name')) }}"
            class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
        <input type="email" name="from_email" id="field-from-email" value="{{ old('from_email', $isEdit ? $template->from_email : '') }}"
            class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Reply-To</label>
        <input type="text" name="reply_to" id="field-reply-to" value="{{ old('reply_to', $isEdit ? $template->reply_to : '') }}"
            class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Optional">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">CC</label>
        <input type="text" name="cc" id="field-cc" value="{{ old('cc', $isEdit ? $template->cc : '') }}"
            class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Comma-separated">
    </div>
</div>

<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
    <input type="text" name="subject" id="field-subject" value="{{ old('subject', $isEdit ? $template->subject : '') }}"
        class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Invoice from {{company_name}}">
</div>

<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-1">Body (HTML)</label>
    <textarea name="body" id="field-body" rows="14"
        class="shortcode-target w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">{{ old('body', $isEdit ? $template->body : '') }}</textarea>
</div>

@if($isEdit)
    <div class="mb-4">
        <label class="inline-flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}
                class="rounded border-gray-300 text-blue-600">
            <span class="ml-2 text-sm text-gray-700">Active</span>
        </label>
    </div>
@endif

<script>
// Track last focused shortcode-target field
let lastTarget = null;
document.querySelectorAll('.shortcode-target').forEach(f => {
    f.addEventListener('focus', () => lastTarget = f);
});
// Click-to-add shortcodes
document.querySelectorAll('.shortcode-insert').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!lastTarget) lastTarget = document.getElementById('field-body');
        const code = this.dataset.code;
        const pos = lastTarget.selectionStart || lastTarget.value.length;
        lastTarget.value = lastTarget.value.substring(0, pos) + code + lastTarget.value.substring(lastTarget.selectionEnd || pos);
        lastTarget.focus();
        lastTarget.selectionStart = lastTarget.selectionEnd = pos + code.length;
    });
});
</script>

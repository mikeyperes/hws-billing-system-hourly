{{-- Invoice Email Center — per-field template dropdowns with shortcode click-to-add --}}
@extends('layouts.app')
@section('title', 'Email Invoice #' . $invoice->id)
@section('header', 'Email: Invoice #' . $invoice->id)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Email Form (2 cols) --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('invoices.send-email', $invoice) }}">
            @csrf
            <input type="hidden" name="template_id" id="active-template-id" value="{{ $primaryTemplate->id ?? '' }}">

            {{-- Base Template Selector --}}
            <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                <label class="block text-xs font-medium text-blue-700 mb-1">Load Template (populates all fields)</label>
                <select id="base-template" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white">
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}"
                            data-from-name="{{ $template->from_name }}"
                            data-from-email="{{ $template->from_email }}"
                            data-subject="{{ $template->subject }}"
                            data-body="{{ e($template->body) }}"
                            {{ $primaryTemplate && $primaryTemplate->id === $template->id ? 'selected' : '' }}>
                            {{ $template->name }} {{ $template->is_primary ? '★' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- To --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="email" name="to_email" id="to-email"
                    value="{{ old('to_email', $invoice->client->email ?? '') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>

            {{-- From --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-gray-700">From</label>
                    <select class="field-template-picker text-xs border border-gray-200 rounded px-2 py-0.5" data-target="from-fields">
                        <option value="">— pick from template —</option>
                        @foreach($templates as $t)
                            @if($t->from_name || $t->from_email)
                                <option data-from-name="{{ $t->from_name }}" data-from-email="{{ $t->from_email }}">{{ $t->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div id="from-fields" class="grid grid-cols-2 gap-2">
                    <input type="text" name="from_name" id="from-name" placeholder="Name" value="{{ $primaryTemplate->from_name ?? config('hws.company_name') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <input type="email" name="from_email" id="from-email" placeholder="Email" value="{{ $primaryTemplate->from_email ?? '' }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            {{-- Subject --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-gray-700">Subject</label>
                    <select class="field-template-picker text-xs border border-gray-200 rounded px-2 py-0.5" data-target="subject-field">
                        <option value="">— pick from template —</option>
                        @foreach($templates as $t)
                            @if($t->subject)
                                <option data-subject="{{ $t->subject }}">{{ $t->name }}: {{ \Illuminate\Support\Str::limit($t->subject, 40) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <input type="text" name="subject" id="subject-field"
                    value="{{ $primaryTemplate->subject ?? '' }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <div class="flex flex-wrap gap-1 mt-1">
                    @foreach(config('hws.shortcodes') as $code => $label)
                        <button type="button" class="shortcode-btn text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded hover:bg-blue-100 hover:text-blue-700"
                            data-target="subject-field" data-code="{{ $code }}">{{ $code }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Body --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-gray-700">Body</label>
                    <select class="field-template-picker text-xs border border-gray-200 rounded px-2 py-0.5" data-target="body-field">
                        <option value="">— pick from template —</option>
                        @foreach($templates as $t)
                            @if($t->body)
                                <option data-body="{{ e($t->body) }}">{{ $t->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <textarea name="body" id="body-field" rows="12"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">{{ $primaryTemplate->body ?? '' }}</textarea>
                <div class="flex flex-wrap gap-1 mt-1">
                    @foreach(config('hws.shortcodes') as $code => $label)
                        <button type="button" class="shortcode-btn text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded hover:bg-blue-100 hover:text-blue-700"
                            data-target="body-field" data-code="{{ $code }}">{{ $code }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Invoice Summary --}}
            <div class="mb-4 p-4 bg-gray-50 rounded-lg text-sm">
                <p class="font-medium text-gray-700 mb-2">Invoice Summary</p>
                <p>Client: {{ $invoice->client->name ?? 'N/A' }} &middot; Hours: {{ $invoice->total_hours }} hrs &middot; Amount: {{ $invoice->formatted_amount }} &middot; Status: {{ ucfirst($invoice->status) }}</p>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700"
                onclick="return confirm('Send to {{ $invoice->client->email ?? 'recipient' }}?')">
                Send Email
            </button>
        </form>
    </div>

    {{-- Shortcode Reference --}}
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Shortcode Values</h3>
            <p class="text-xs text-gray-500 mb-3">Click any shortcode to insert into the focused field.</p>
            <div class="space-y-2 text-sm">
                @foreach($shortcodes as $code => $value)
                    <div>
                        <code class="text-xs font-mono text-blue-600">{{ $code }}</code>
                        <p class="text-xs text-gray-500 truncate">{{ \Illuminate\Support\Str::limit(strip_tags($value), 60) }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Actions</h3>
            <div class="space-y-2">
                @if($invoice->stripe_invoice_id)
                    <form method="POST" action="{{ route('invoices.send-stripe', $invoice) }}">@csrf
                        <button class="w-full text-xs bg-purple-100 text-purple-700 px-3 py-2 rounded hover:bg-purple-200">Send via Stripe Email</button>
                    </form>
                @endif
                <a href="{{ route('invoices.show', $invoice) }}" class="block text-xs text-center bg-gray-100 text-gray-700 px-3 py-2 rounded hover:bg-gray-200">Back to Invoice</a>
                <a href="{{ route('emails.index') }}" class="block text-xs text-center text-blue-600 hover:underline">Manage Email Templates</a>
            </div>
        </div>
    </div>
</div>

<script>
// Base template loader — populates all fields
document.getElementById('base-template').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    document.getElementById('active-template-id').value = opt.value;
    document.getElementById('from-name').value = opt.dataset.fromName || '';
    document.getElementById('from-email').value = opt.dataset.fromEmail || '';
    document.getElementById('subject-field').value = opt.dataset.subject || '';
    // Decode HTML entities for body
    const txt = document.createElement('textarea');
    txt.innerHTML = opt.dataset.body || '';
    document.getElementById('body-field').value = txt.value;
});

// Per-field template pickers
document.querySelectorAll('.field-template-picker').forEach(picker => {
    picker.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const target = this.dataset.target;
        if (opt.dataset.fromName !== undefined) {
            document.getElementById('from-name').value = opt.dataset.fromName || '';
            document.getElementById('from-email').value = opt.dataset.fromEmail || '';
        }
        if (opt.dataset.subject !== undefined) {
            document.getElementById('subject-field').value = opt.dataset.subject || '';
        }
        if (opt.dataset.body !== undefined) {
            const txt = document.createElement('textarea');
            txt.innerHTML = opt.dataset.body;
            document.getElementById('body-field').value = txt.value;
        }
        this.selectedIndex = 0;
    });
});

// Click-to-add shortcodes
let lastFocusedField = null;
document.querySelectorAll('#subject-field, #body-field, #from-name, #from-email').forEach(f => {
    f.addEventListener('focus', () => lastFocusedField = f);
});
document.querySelectorAll('.shortcode-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const target = this.dataset.target ? document.getElementById(this.dataset.target) : lastFocusedField;
        if (!target) return;
        const code = this.dataset.code;
        const pos = target.selectionStart || target.value.length;
        target.value = target.value.substring(0, pos) + code + target.value.substring(target.selectionEnd || pos);
        target.focus();
        target.selectionStart = target.selectionEnd = pos + code.length;
    });
});
</script>
@endsection

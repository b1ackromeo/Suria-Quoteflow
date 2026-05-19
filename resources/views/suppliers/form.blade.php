@extends('layouts.app', [
    'title' => $supplier->exists ? 'Edit Supplier' : 'New Supplier',
    'contentMode' => 'fullscreen',
])

@php
    $isEditing = $supplier->exists;
    $addressValue = old('address', $supplier->address);
    $addressLines = preg_split('/\r\n|\r|\n/', trim((string) $addressValue));
    $addressLines = array_values(array_filter($addressLines, fn ($line) => trim((string) $line) !== ''));
    $addressLines = array_pad(array_slice($addressLines, 0, 4), 4, '');
    $categoryValue = old('category', $supplier->category);
@endphp

@section('content')
<form
    method="post"
    action="{{ $isEditing ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
    class="fullscreen-workspace supplier-form-workspace"
    data-supplier-form
>
    @csrf
    @if($isEditing)
        @method('put')
    @endif

    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Supplier directory</p>
            <h1 class="document-pane-title">{{ $isEditing ? 'Edit supplier' : 'New supplier' }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Keep supplier identity, contact, address, and default terms ready for procurement documents and invoice matching.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-secondary" href="{{ route('suppliers.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save supplier</button>
        </div>
    </section>

    <div class="supplier-form-body">
        <main class="supplier-form-main">
            <section class="directory-form-section">
                <div class="supplier-form-compact-header">
                    <p class="studio-section-kicker">Supplier record</p>
                    <h2 class="studio-section-title">Supplier identity</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="form-label md:col-span-2">
                        Supplier name
                        <input class="form-input" name="name" value="{{ old('name', $supplier->name) }}" data-supplier-name placeholder="Best Supplies Sdn Bhd" required>
                    </label>
                    <label class="form-label">
                        Supplier code
                        <input class="form-input" name="code" value="{{ old('code', $supplier->code) }}" data-supplier-code placeholder="BESTSUP">
                    </label>
                    <label class="form-label">
                        Supplier category
                        <input class="form-input" name="category" value="{{ $categoryValue }}" data-supplier-category list="supplier-category-options" placeholder="Materials / Hardware">
                        <datalist id="supplier-category-options">
                            @foreach($categoryOptions as $categoryOption)
                                <option value="{{ $categoryOption }}"></option>
                            @endforeach
                        </datalist>
                    </label>
                    <label class="form-label">
                        Contact person
                        <input class="form-input" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" data-supplier-contact placeholder="Primary procurement contact">
                    </label>
                    <label class="form-label">
                        Email
                        <input class="form-input" type="email" name="email" value="{{ old('email', $supplier->email) }}" data-supplier-email placeholder="supplier@example.com">
                    </label>
                    <label class="form-label">
                        Phone
                        <input class="form-input" name="phone" value="{{ old('phone', $supplier->phone) }}" data-supplier-phone placeholder="+60">
                    </label>
                    <label class="form-label">
                        Tax / registration number
                        <input class="form-input" name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}" data-supplier-tax placeholder="Company registration or tax reference">
                    </label>
                    <label class="form-label md:col-span-2 xl:col-span-2">
                        Default payment term (days)
                        <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Simple day-based default only. Staged terms are set on the purchase order or supplier invoice.</span>
                        <input class="form-input" type="number" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" data-supplier-term min="0" max="365" required>
                    </label>
                    <label class="directory-toggle-card md:col-span-2 xl:col-span-2">
                        <input type="checkbox" name="is_active" value="1" data-supplier-active @checked(old('is_active', $supplier->is_active))>
                        <span>
                            <strong>Active supplier</strong>
                            <small>Allow this supplier to be selected in new procurement documents.</small>
                        </span>
                    </label>
                </div>

                <textarea class="hidden" name="address" data-address-output="supplier">{{ $addressValue }}</textarea>

                <section class="supplier-address-block">
                    <div class="supplier-address-heading">
                        <div>
                            <h3>Address block</h3>
                            <p>Shown on procurement documents, matching screens, and search.</p>
                        </div>
                        <span>Supplier</span>
                    </div>
                    <div class="address-field-grid md:grid-cols-2">
                        <label class="address-line-field">
                            Address line 1
                            <input class="form-input" data-address-input="supplier" value="{{ $addressLines[0] }}" data-supplier-address-line placeholder="Company / building / street">
                        </label>
                        <label class="address-line-field">
                            Address line 2
                            <input class="form-input" data-address-input="supplier" value="{{ $addressLines[1] }}" data-supplier-address-line placeholder="Unit, floor, area">
                        </label>
                        <label class="address-line-field">
                            City, state, postcode
                            <input class="form-input" data-address-input="supplier" value="{{ $addressLines[2] }}" data-supplier-address-line placeholder="Kuala Lumpur 50000">
                        </label>
                        <label class="address-line-field">
                            Country
                            <input class="form-input" data-address-input="supplier" value="{{ $addressLines[3] }}" data-supplier-address-line placeholder="Malaysia">
                        </label>
                    </div>
                </section>
            </section>
        </main>

        <aside class="supplier-preview-pane">
            <section class="supplier-preview-card">
                <p class="document-pane-kicker">Supplier preview</p>
                <h2>Supplier preview</h2>
                <div class="supplier-preview-block">
                    <div class="supplier-preview-heading">
                        <div class="supplier-directory-avatar" data-preview-initial>{{ mb_substr(old('name', $supplier->name) ?: 'S', 0, 1) }}</div>
                        <div class="min-w-0">
                            <strong data-preview-name>{{ old('name', $supplier->name) ?: 'Supplier name' }}</strong>
                            <span data-preview-code>{{ old('code', $supplier->code) ?: 'No supplier code' }}</span>
                            <span data-preview-category>{{ $categoryValue ?: 'Uncategorised' }}</span>
                        </div>
                    </div>

                    <dl class="supplier-preview-details">
                        <div>
                            <dt>Category</dt>
                            <dd data-preview-category-detail>{{ $categoryValue ?: 'Uncategorised' }}</dd>
                        </div>
                        <div>
                            <dt>Contact</dt>
                            <dd data-preview-contact>{{ old('contact_person', $supplier->contact_person) ?: 'No contact person' }}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd data-preview-email>{{ old('email', $supplier->email) ?: 'No email' }}</dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd data-preview-phone>{{ old('phone', $supplier->phone) ?: 'No phone' }}</dd>
                        </div>
                        <div>
                            <dt>Default payment term</dt>
                            <dd><span data-preview-term>{{ old('payment_terms_days', $supplier->payment_terms_days) }}</span> days after invoice</dd>
                        </div>
                    </dl>

                    <div class="supplier-preview-address">
                        <span>Address</span>
                        <p data-preview-address>{{ trim((string) $addressValue) ?: 'No address added yet.' }}</p>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</form>

<script>
(() => {
    const form = document.querySelector('[data-supplier-form]');
    if (!form) return;

    const output = form.querySelector('[data-address-output="supplier"]');
    const addressInputs = Array.from(form.querySelectorAll('[data-address-input="supplier"]'));

    function inputValue(selector, fallback = '') {
        const value = form.querySelector(selector)?.value?.trim();
        return value || fallback;
    }

    function setText(selector, value) {
        const target = form.querySelector(selector);
        if (target) target.textContent = value;
    }

    function composeAddress() {
        const address = addressInputs
            .map((field) => field.value.trim())
            .filter(Boolean)
            .join('\n');

        if (output) output.value = address;
        setText('[data-preview-address]', address || 'No address added yet.');
    }

    function refreshPreview() {
        const name = inputValue('[data-supplier-name]', 'Supplier name');

        setText('[data-preview-name]', name);
        setText('[data-preview-initial]', name.substring(0, 1).toUpperCase());
        setText('[data-preview-code]', inputValue('[data-supplier-code]', 'No supplier code'));
        setText('[data-preview-category]', inputValue('[data-supplier-category]', 'Uncategorised'));
        setText('[data-preview-category-detail]', inputValue('[data-supplier-category]', 'Uncategorised'));
        setText('[data-preview-contact]', inputValue('[data-supplier-contact]', 'No contact person'));
        setText('[data-preview-email]', inputValue('[data-supplier-email]', 'No email'));
        setText('[data-preview-phone]', inputValue('[data-supplier-phone]', 'No phone'));
        setText('[data-preview-term]', inputValue('[data-supplier-term]', '0'));
        composeAddress();
    }

    form.querySelectorAll('input, textarea, select').forEach((field) => {
        field.addEventListener('input', refreshPreview);
        field.addEventListener('change', refreshPreview);
    });

    form.addEventListener('submit', composeAddress);
    refreshPreview();
})();
</script>
@endsection

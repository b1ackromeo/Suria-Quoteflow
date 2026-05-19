@extends('layouts.app', [
    'title' => $customer->exists ? 'Edit Customer' : 'New Customer',
    'contentMode' => 'fullscreen',
])

@php
    $isEditing = $customer->exists;
    $billingAddressValue = old('billing_address', $customer->billing_address);
    $shippingAddressValue = old('shipping_address', $customer->shipping_address);
    $splitAddress = function ($value) {
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $value));
        $lines = array_values(array_filter($lines, fn ($line) => trim((string) $line) !== ''));

        return array_pad(array_slice($lines, 0, 4), 4, '');
    };
    $billingLines = $splitAddress($billingAddressValue);
    $shippingLines = $splitAddress($shippingAddressValue);
    $sameAsBilling = old('same_as_billing_address') !== null
        ? old('same_as_billing_address') === '1'
        : ((! $isEditing && trim((string) $shippingAddressValue) === '') || (trim((string) $billingAddressValue) !== '' && trim((string) $billingAddressValue) === trim((string) $shippingAddressValue)));
@endphp

@section('content')
<form
    method="post"
    action="{{ $isEditing ? route('customers.update', $customer) : route('customers.store') }}"
    class="fullscreen-workspace directory-form-workspace"
>
    @csrf
    @if($isEditing)
        @method('put')
    @endif

    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Customer Directory</p>
            <h1 class="document-pane-title">{{ $isEditing ? 'Edit Customer' : 'New Customer' }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Keep customer identity, contact, payment terms, and addresses clean so quotations, customer POs, and invoices are prepared correctly.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-secondary" href="{{ route('customers.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save customer</button>
        </div>
    </section>

    <div class="directory-form-body">
        <section class="directory-form-section">
            <div class="directory-form-section-header">
                <div>
                    <p class="studio-section-kicker">Account details</p>
                    <h2 class="studio-section-title">Customer profile</h2>
                    <p class="studio-section-copy">Use the registered or trading name your team expects to see on customer-facing documents.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="form-label">Customer name
                    <input class="form-input" name="name" value="{{ old('name', $customer->name) }}" placeholder="Acme Trading Sdn Bhd" required>
                </label>
                <label class="form-label">Customer code
                    <input class="form-input" name="code" value="{{ old('code', $customer->code) }}" placeholder="ACME">
                </label>
                <label class="form-label">Contact person
                    <input class="form-input" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}" placeholder="Primary contact name">
                </label>
                <label class="form-label">Email
                    <input class="form-input" type="email" name="email" value="{{ old('email', $customer->email) }}" placeholder="customer@example.com">
                </label>
                <label class="form-label">Phone
                    <input class="form-input" name="phone" value="{{ old('phone', $customer->phone) }}" placeholder="+60">
                </label>
                <label class="form-label md:col-span-2">Tax / registration number
                    <input class="form-input" name="tax_number" value="{{ old('tax_number', $customer->tax_number) }}" placeholder="Company registration or tax reference">
                </label>
                <label class="directory-toggle-card">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                    <span>
                        <strong>Active customer</strong>
                        <small>Allow this customer to be selected in new documents.</small>
                    </span>
                </label>
            </div>

            <div class="directory-default-card mt-5">
                <div>
                    <p class="studio-section-kicker">Document default</p>
                    <h3>Default simple payment term</h3>
                    <p>Used only to prefill standard day-based quotation or invoice terms. Milestone and progress billing terms are set on the actual quotation or invoice.</p>
                </div>
                <label class="form-label">Days after invoice
                    <input class="form-input" type="number" name="payment_terms_days" value="{{ old('payment_terms_days', $customer->payment_terms_days) }}" min="0" max="365" required>
                </label>
            </div>
        </section>

        <section class="directory-form-section">
            <div class="directory-form-section-header">
                <div>
                    <p class="studio-section-kicker">Address details</p>
                    <h2 class="studio-section-title">Customer address book</h2>
                    <p class="studio-section-copy">Enter the address in business-document order. The system saves it as a clean address block for PDFs and customer records.</p>
                </div>
            </div>

            <textarea class="hidden" name="billing_address" data-address-output="billing">{{ $billingAddressValue }}</textarea>
            <textarea class="hidden" name="shipping_address" data-address-output="shipping">{{ $shippingAddressValue }}</textarea>

            <div class="address-builder-grid">
                <section class="address-builder-card">
                    <div class="address-builder-heading">
                        <div>
                            <h3>Billing address</h3>
                            <p>Appears on quotations, invoices, and customer statements.</p>
                        </div>
                        <span>Billing</span>
                    </div>
                    <div class="address-field-grid">
                        <label class="address-line-field">Address line 1
                            <input class="form-input" data-address-input="billing" value="{{ $billingLines[0] }}" placeholder="Company / building / street">
                        </label>
                        <label class="address-line-field">Address line 2
                            <input class="form-input" data-address-input="billing" value="{{ $billingLines[1] }}" placeholder="Unit, floor, area">
                        </label>
                        <label class="address-line-field">City, state, postcode
                            <input class="form-input" data-address-input="billing" value="{{ $billingLines[2] }}" placeholder="Cyberjaya, Selangor 63000">
                        </label>
                        <label class="address-line-field">Country
                            <input class="form-input" data-address-input="billing" value="{{ $billingLines[3] }}" placeholder="Malaysia">
                        </label>
                    </div>
                </section>

                <section class="address-builder-card">
                    <div class="address-builder-heading">
                        <div>
                            <h3>Delivery / service address</h3>
                            <p>Used for delivery, installation, service visits, and handover records.</p>
                        </div>
                        <label class="same-address-toggle">
                            <input type="checkbox" name="same_as_billing_address" value="1" data-same-as-billing @checked($sameAsBilling)>
                            <span>Same as billing</span>
                        </label>
                    </div>
                    <div class="address-field-grid">
                        <label class="address-line-field">Address line 1
                            <input class="form-input" data-address-input="shipping" value="{{ $sameAsBilling ? $billingLines[0] : $shippingLines[0] }}" placeholder="Delivery site / building / street">
                        </label>
                        <label class="address-line-field">Address line 2
                            <input class="form-input" data-address-input="shipping" value="{{ $sameAsBilling ? $billingLines[1] : $shippingLines[1] }}" placeholder="Unit, floor, area">
                        </label>
                        <label class="address-line-field">City, state, postcode
                            <input class="form-input" data-address-input="shipping" value="{{ $sameAsBilling ? $billingLines[2] : $shippingLines[2] }}" placeholder="Cyberjaya, Selangor 63000">
                        </label>
                        <label class="address-line-field">Country
                            <input class="form-input" data-address-input="shipping" value="{{ $sameAsBilling ? $billingLines[3] : $shippingLines[3] }}" placeholder="Malaysia">
                        </label>
                    </div>
                </section>
            </div>
        </section>
    </div>
</form>

<script>
(() => {
    const form = document.querySelector('[data-address-output="billing"]')?.closest('form');
    const sameAsBilling = document.querySelector('[data-same-as-billing]');
    const groups = ['billing', 'shipping'];

    function inputsFor(group) {
        return Array.from(document.querySelectorAll(`[data-address-input="${group}"]`));
    }

    function compose(group) {
        const output = document.querySelector(`[data-address-output="${group}"]`);
        if (!output) return;

        output.value = inputsFor(group)
            .map((field) => field.value.trim())
            .filter(Boolean)
            .join('\n');
    }

    function syncShippingFromBilling() {
        const billingFields = inputsFor('billing');
        inputsFor('shipping').forEach((field, index) => {
            field.value = billingFields[index]?.value || '';
        });
        compose('shipping');
    }

    function setShippingMode() {
        const locked = sameAsBilling?.checked ?? false;
        if (locked) syncShippingFromBilling();

        inputsFor('shipping').forEach((field) => {
            field.readOnly = locked;
            field.classList.toggle('address-input-readonly', locked);
        });
    }

    inputsFor('billing').forEach((field) => {
        field.addEventListener('input', () => {
            compose('billing');
            if (sameAsBilling?.checked) syncShippingFromBilling();
        });
    });

    inputsFor('shipping').forEach((field) => {
        field.addEventListener('input', () => compose('shipping'));
    });

    sameAsBilling?.addEventListener('change', setShippingMode);
    form?.addEventListener('submit', () => groups.forEach(compose));

    groups.forEach(compose);
    setShippingMode();
})();
</script>
@endsection

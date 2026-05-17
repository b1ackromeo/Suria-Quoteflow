@extends('layouts.app', [
    'title' => 'Create Purchase Request from Supplier Quote',
    'contentMode' => 'fullscreen',
])

@section('header_actions')
<div class="flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="{{ route('documents.index', 'purchase-requests') }}">Back</a>
</div>
@endsection

@section('content')
<div class="fullscreen-workspace document-open-workspace">
    <section class="document-open-preview-pane" aria-label="Supplier quotation source">
        <div class="document-open-heading">
            <div class="min-w-0">
                <p class="document-pane-kicker">Quote-first procurement</p>
                <h1>Create Purchase Request from supplier quote</h1>
                <p>Upload the supplier quotation first. QuoteFlow will create a draft PR, run OCR, and show the extracted quote lines for verification.</p>
            </div>
        </div>

        <article class="external-document-preview-card">
            <div class="external-document-preview-header">
                <div>
                    <p class="document-pane-kicker">Source document</p>
                    <h3>Supplier quotation PDF or image</h3>
                </div>
                <span class="status-chip status-draft">Required</span>
            </div>

            <div class="external-document-record-only">
                <h4>Workflow</h4>
                <p>Supplier quotation file -> OCR quote fields and line items -> verify or correct -> PR line items are populated -> submit for approval.</p>
            </div>
        </article>
    </section>

    <aside class="document-open-side-panel" aria-label="Create purchase request">
        <form method="post" action="{{ route('purchase-requests.quote-first.store') }}" enctype="multipart/form-data" class="document-side-card space-y-4">
            @csrf
            <div>
                <p class="document-pane-kicker">Start from quote</p>
                <h2 class="panel-title">Upload supplier quotation</h2>
            </div>

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <label class="form-label">Supplier
                <select class="form-input" name="supplier_id">
                    <option value="">Select supplier if known</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-label">Supplier quotation file
                <input class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-slate-700 hover:file:bg-slate-200" type="file" name="source_attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tif,.tiff" required>
                <span class="mt-1 block text-xs font-semibold text-slate-500">PDF or image. This becomes the PR supplier_quote evidence.</span>
            </label>

            <label class="form-label">Request date
                <input class="form-input" type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required>
            </label>

            <label class="form-label">Currency
                <input class="form-input uppercase" name="currency" maxlength="3" value="{{ old('currency', 'MYR') }}" required>
            </label>

            <label class="form-label">Project / site
                <input class="form-input" name="project_name" value="{{ old('project_name') }}" placeholder="Cyberjaya Site">
            </label>

            <label class="form-label">Delivery / service location
                <textarea class="form-input min-h-24" name="delivery_to" placeholder="Site address, delivery contact, handover location">{{ old('delivery_to') }}</textarea>
            </label>

            <label class="form-label">Source note
                <textarea class="form-input min-h-20" name="source_note" placeholder="Optional context for the supplier quotation or requested purchase.">{{ old('source_note') }}</textarea>
            </label>

            <button class="btn btn-primary w-full" type="submit">Create draft and run OCR</button>
        </form>
    </aside>
</div>
@endsection

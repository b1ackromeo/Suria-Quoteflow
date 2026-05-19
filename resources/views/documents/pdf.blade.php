@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $companyAddress = trim(preg_replace('/\s+/', ' ', (string) $companyProfile->address));
    $companyContactLine = collect([$companyProfile->email, $companyProfile->phone])->filter()->implode(' | ');
    $companyInitials = collect(preg_split('/\s+/', trim($companyProfile->displayName())))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => strtoupper(substr($word, 0, 1)))
        ->implode('') ?: 'CO';
    $primaryColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $companyProfile->primary_color)
        ? $companyProfile->primary_color
        : '#0a345f';
    $accentColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $companyProfile->accent_color)
        ? $companyProfile->accent_color
        : '#1d7df2';
    $company = [
        'name' => strtoupper($companyProfile->displayName()),
        'reg' => $companyProfile->registration_number,
        'address' => $companyProfile->address,
        'address_inline' => $companyAddress,
        'email' => $companyProfile->email,
        'phone' => $companyProfile->phone,
        'contact_line' => $companyContactLine,
        'tagline' => $companyProfile->displayTagline(),
    ];

    $logoPath = $companyProfile->logoPathForPdf();
    $isInvoice = $document->isInvoice();
    $isSupplierPo = $document->type === 'supplier_po';
    $isCustomerPo = $document->type === 'customer_po';
    $isGoodsReceipt = $document->type === 'goods_receipt';
    $isQuotation = $document->isQuotation();
    $receiptLineTypes = $isGoodsReceipt
        ? $document->items->map(function ($item) {
            $type = strtolower((string) ($item->product?->type ?? ''));

            if (in_array($type, ['product', 'service'], true)) {
                return $type;
            }

            $unit = strtolower((string) $item->unit);

            return in_array($unit, ['job', 'lot', 'hour', 'day', 'month'], true) ? 'service' : 'product';
        })
        : collect();
    $receiptLineCount = $receiptLineTypes->count();
    $receiptKind = $receiptLineCount > 0 && $receiptLineTypes->every(fn ($type) => $type === 'service')
        ? 'service'
        : ($receiptLineCount > 0 && $receiptLineTypes->every(fn ($type) => $type === 'product') ? 'goods' : 'mixed');
    $receiptDocumentTitle = match ($receiptKind) {
        'service' => 'SERVICE ACCEPTANCE RECORD',
        'goods' => 'GOODS RECEIPT NOTE',
        default => 'RECEIVING & ACCEPTANCE RECORD',
    };
    $receiptPartyTitle = $receiptKind === 'service' ? 'Service Provider' : 'Supplier';
    $receiptDetailsTitle = $receiptKind === 'service' ? 'Service Acceptance Details' : ($receiptKind === 'goods' ? 'Goods Receipt Details' : 'Receipt Details');
    $receiptEvidenceAttachments = $isGoodsReceipt ? $document->attachments->values() : collect();
    $receiptEvidenceLabels = [
        'delivery_order' => 'Delivery order',
        'service_report' => 'Service report',
        'delivery_evidence' => 'Delivery / service evidence',
        'uat_document' => 'UAT / acceptance sign-off',
        'installation_report' => 'Installation report',
        'completion_photo' => 'Completion photo',
        'supporting_document' => 'Supporting document',
        'email_approval' => 'Email approval',
    ];

    $title = match ($document->type) {
        'customer_quotation', 'supplier_quotation' => 'QUOTATION',
        'customer_po' => 'CUSTOMER PO RECEIVED',
        'supplier_po' => 'PURCHASE ORDER',
        'customer_invoice', 'supplier_invoice' => 'INVOICE',
        'goods_receipt' => $receiptDocumentTitle,
        'purchase_request' => 'PURCHASE REQUEST',
        default => strtoupper($meta['singular']),
    };
    $documentContext = match ($document->type) {
        'customer_quotation' => 'Customer sales document',
        'customer_po' => 'Customer PO received',
        'customer_invoice' => 'Customer billing document',
        'purchase_request' => 'Procurement request',
        'supplier_quotation' => 'Supplier quotation record',
        'supplier_po' => 'Supplier order document',
        'goods_receipt' => $receiptKind === 'service' ? 'Service acceptance document' : ($receiptKind === 'goods' ? 'Goods receipt document' : 'Goods receipt and acceptance document'),
        'supplier_invoice' => 'Supplier invoice record',
        default => 'Business document',
    };

    $party = $document->customer ?? $document->supplier;
    $partyAddress = $document->customer?->billing_address
        ?? $document->customer?->shipping_address
        ?? $document->supplier?->address
        ?? null;
    $partyEmail = $document->customer?->email ?? $document->supplier?->email;
    $partyContact = $document->customer?->contact_person ?? $document->supplier?->contact_person;
    $relatedRef = $document->relatedDocument?->external_reference
        ?: $document->relatedDocument?->document_number
        ?: $document->external_reference;
    $projectName = $document->project_name ?: 'Cyberjaya Site';
    $deliveryTo = $document->delivery_to ?: $projectName;
    $paymentStages = $document->billingStages;
    $sourceItems = $document->relatedDocument?->items ?? collect();
    $defaultTerms = match ($document->type) {
        'supplier_po' => [
            'Supplier invoice must state PO number, billing stage and supporting DO/acceptance reference.',
            'Payment period starts after '.$companyProfile->displayName().' receives a valid invoice for accepted goods/services.',
            'Any price, quantity or scope change requires written approval.',
        ],
        'customer_quotation', 'supplier_quotation' => [
            'This quotation is valid until the date stated above.',
            'Prices are subject to confirmed scope, site access and agreed work schedule.',
            'Each progress invoice will state the billing stage, invoice amount and due date.',
            'Any additional work must be confirmed in writing before execution.',
        ],
        default => [],
    };

    $terms = filled($document->terms)
        ? preg_split('/\r\n|\r|\n/', trim($document->terms))
        : $defaultTerms;
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->document_number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #001d3d;
            font-family: Helvetica, Arial, DejaVu Sans, sans-serif;
            font-size: 10.7px;
            line-height: 1.42;
            background: #fff;
        }
        .page { position: relative; min-height: 1123px; padding: 0 44px 34px; }
        .masthead {
            margin: 0 -44px 25px;
            padding: 26px 44px 0;
            background: #fff;
            color: #001d3d;
        }
        .masthead-table { width: 100%; border-collapse: collapse; margin: 0; }
        .masthead-table td { border: 0; padding: 0; vertical-align: top; }
        .brand-table { border-collapse: collapse; margin: 0; }
        .brand-table td { border: 0; padding: 0; vertical-align: middle; }
        .logo-frame {
            width: 72px;
            height: 72px;
            padding: 0;
            background: #fff;
        }
        .logo {
            width: 72px;
            height: 72px;
            border-radius: 0;
            background: #fff;
        }
        .logo-initials {
            width: 72px;
            height: 72px;
            background: {{ $accentColor }};
            color: #fff;
            font-size: 21px;
            line-height: 72px;
            text-align: center;
            font-weight: 700;
            letter-spacing: .8px;
        }
        .brand-copy {
            padding-left: 14px;
        }
        .brand-name {
            margin-left: 0;
            color: {{ $primaryColor }};
            font-size: 22px;
            line-height: 1.05;
            font-weight: 700;
            letter-spacing: .25px;
        }
        .brand-tagline {
            margin-top: 4px;
            margin-left: 0;
            color: {{ $accentColor }};
            font-size: 9.4px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: .2px;
        }
        .company-meta {
            margin-top: 5px;
            margin-left: 0;
            color: #344054;
            font-size: 7.9px;
            line-height: 1.3;
        }
        .doc-title {
            text-align: right;
            padding-top: 4px;
            padding-left: 18px;
        }
        .doc-context {
            margin: 0 0 6px;
            color: #607084;
            font-size: 7.5px;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .doc-title h1 {
            margin: 0;
            color: {{ $primaryColor }};
            font-size: 14px;
            line-height: 1.18;
            letter-spacing: .8px;
            font-weight: 700;
        }
        .doc-number {
            margin-top: 8px;
            padding: 6px 0 0;
            border: 0;
            border-top: 2px solid {{ $accentColor }};
            color: #001d3d;
            font-size: 10.2px;
            font-weight: 700;
            display: inline-block;
            min-width: 128px;
            text-align: right;
            background: transparent;
        }
        .doc-number span {
            display: block;
            margin-bottom: 2px;
            color: #607084;
            font-size: 7.6px;
            line-height: 1;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
        }
        .masthead-rule {
            height: 5px;
            margin: 18px -44px 0;
            border-top: 1px solid #d8e2ee;
            border-bottom: 4px solid {{ $primaryColor }};
        }
        .layout-table { width: 100%; border-collapse: collapse; margin: 0; }
        .layout-table td { border: 0; padding: 0; vertical-align: top; }
        .col-gap { width: 22px; }
        .tri-gap { width: 14px; }
        .section-title {
            margin: 0 0 7px;
            color: #0a345f;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .9px;
        }
        .card {
            min-height: 96px;
            border: 1px solid #d8e2ee;
            padding: 13px 15px;
        }
        .card.accent { border-left: 4px solid #1d7df2; }
        .card h2 { margin: 0 0 8px; font-size: 14.2px; line-height: 1.2; }
        .muted { color: #344054; }
        .details {
            border: 1px solid #d8e2ee;
            background: #f6f9fc;
            padding: 10px 14px;
        }
        .detail-row {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .detail-row td {
            border: 0;
            border-bottom: 1px solid #e2ebf4;
            padding: 4px 0;
        }
        .detail-row tr:last-child td { border-bottom: 0; }
        .label { color: #607084; }
        .value { text-align: right; font-weight: 700; color: #001d3d; }
        .info-strip {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0 0;
            border: 1px solid #d8e2ee;
        }
        .info-strip td {
            width: 25%;
            border-right: 1px solid #d8e2ee;
            padding: 10px 13px;
            background: #fbfdff;
            vertical-align: top;
        }
        .info-strip td:last-child { border-right: 0; }
        .info-strip span {
            display: block;
            color: #607084;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }
        .info-strip strong { display: block; margin-top: 4px; font-size: 10.7px; }
        .items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .items th {
            background: #0a345f;
            color: #fff;
            padding: 8px 7px;
            font-size: 8.8px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .items td {
            border-bottom: 1px solid #d8e2ee;
            padding: 10px 7px;
            vertical-align: top;
        }
        .receipt-status {
            display: inline-block;
            margin-top: 7px;
            padding: 5px 11px;
            border: 1px solid #9ee6c4;
            border-radius: 12px;
            color: #047857;
            background: #ecfdf5;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
        }
        .receipt-summary {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0 0;
        }
        .receipt-summary td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .receipt-fact-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d8e2ee;
            margin: 0;
        }
        .receipt-fact-grid td {
            width: 50%;
            border-right: 1px solid #d8e2ee;
            border-bottom: 1px solid #d8e2ee;
            padding: 10px 12px;
            background: #fbfdff;
            vertical-align: top;
        }
        .receipt-fact-grid tr:last-child td { border-bottom: 0; }
        .receipt-fact-grid td:last-child { border-right: 0; }
        .receipt-fact-grid span {
            display: block;
            color: #607084;
            font-size: 8.7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
        }
        .receipt-fact-grid strong {
            display: block;
            margin-top: 4px;
            color: #001d3d;
            font-size: 10.7px;
        }
        .receipt-note {
            margin-top: 16px;
            border-top: 3px solid #1d7df2;
            background: #fbfdff;
            padding: 12px 14px;
        }
        .receipt-note h3 {
            margin: 0 0 8px;
            color: #0a345f;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
        }
        .receipt-purpose {
            margin-top: 15px;
            border: 1px solid #c9d8ea;
            background: #edf6ff;
            padding: 10px 12px;
            color: #0a345f;
            font-size: 9.8px;
            font-weight: 700;
        }
        .receipt-evidence {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #d8e2ee;
        }
        .receipt-evidence th {
            padding: 7px 8px;
            background: #edf4fb;
            color: #0a345f;
            font-size: 8.5px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .45px;
        }
        .receipt-evidence td {
            border-top: 1px solid #d8e2ee;
            padding: 8px;
            vertical-align: top;
        }
        .receipt-signature .signature {
            min-height: 92px;
        }
        .desc { font-weight: 700; }
        .right { text-align: right; }
        .schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 13px;
            border: 1px solid #d8e2ee;
        }
        .schedule-title {
            padding: 9px 12px;
            border-bottom: 1px solid #d8e2ee;
            color: #0a345f;
            font-size: 10.2px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            background: #fbfdff;
        }
        .schedule table { width: 100%; border-collapse: collapse; margin: 0; }
        .schedule th {
            padding: 6px 7px;
            background: #edf4fb;
            color: #0a345f;
            font-size: 8.3px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .schedule td {
            border-top: 1px solid #d8e2ee;
            padding: 6px 7px;
            font-size: 9.4px;
        }
        .schedule .current td { background: #e8f2ff; font-weight: 700; }
        .lower { width: 100%; border-collapse: collapse; margin-top: 17px; }
        .lower td { border: 0; padding: 0; vertical-align: top; }
        .terms {
            border-top: 3px solid #1d7df2;
            background: #fbfdff;
            padding: 12px 14px;
        }
        .terms h3,
        .payment h3 {
            margin: 0 0 8px;
            color: #0a345f;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
        }
        .terms ul { margin: 0; padding-left: 16px; }
        .terms li { margin-bottom: 3px; color: #344054; }
        .totals { width: 100%; border-collapse: collapse; border: 1px solid #d8e2ee; margin: 0; }
        .totals td { border-bottom: 1px solid #d8e2ee; padding: 8px 12px; }
        .totals tr:last-child td { border-bottom: 0; }
        .totals .grand td {
            background: #0a345f;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
        }
        .totals .due td { background: #e8f2ff; color: #0a345f; font-weight: 700; }
        .payment {
            border: 1px solid #d8e2ee;
            background: #fbfdff;
            padding: 12px 15px;
        }
        .amount-due {
            margin-top: 13px;
            border: 1px solid #d8e2ee;
            background: #e8f2ff;
            padding: 13px 16px;
        }
        .amount-due span {
            color: #0a345f;
            font-size: 9.4px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .9px;
        }
        .amount-due strong {
            display: block;
            margin-top: 6px;
            color: #0a345f;
            font-size: 22px;
            line-height: 1;
        }
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .signature-table td { border: 0; padding: 0; vertical-align: top; }
        .signature {
            min-height: 78px;
            border: 1px solid #d8e2ee;
            padding: 13px;
        }
        .signature strong { font-size: 12px; }
        .sign-line {
            margin-top: 27px;
            padding-top: 7px;
            border-top: 1px solid #98a2b3;
            color: #607084;
            font-size: 9.5px;
        }
        .footer {
            position: absolute;
            left: 44px;
            right: 44px;
            bottom: 24px;
            padding-top: 10px;
            border-top: 1px solid #d8e2ee;
            color: #667085;
            font-size: 9px;
        }
        .footer strong { color: #0a345f; }
        .receipt-page {
            min-height: 1010px;
            padding-left: 36px;
            padding-right: 36px;
            padding-bottom: 22px;
        }
        .receipt-page .masthead {
            margin-left: -36px;
            margin-right: -36px;
            margin-bottom: 18px;
            padding-left: 36px;
            padding-right: 36px;
        }
        .receipt-page .masthead-rule { margin-left: -36px; margin-right: -36px; }
        .receipt-page .card { min-height: 78px; padding: 10px 12px; }
        .receipt-page .card h2 { margin-bottom: 5px; font-size: 13px; }
        .receipt-page .details { padding: 8px 12px; }
        .receipt-page .detail-row td { padding: 3px 0; }
        .receipt-page .receipt-status { margin-top: 5px; padding: 4px 9px; }
        .receipt-page .receipt-purpose { margin-top: 10px; padding: 7px 10px; font-size: 9.1px; }
        .receipt-page .receipt-summary { margin-top: 10px; }
        .receipt-page .receipt-fact-grid td { padding: 7px 9px; }
        .receipt-page .receipt-note { margin-top: 10px; padding: 9px 11px; }
        .receipt-page .items { margin-top: 11px; }
        .receipt-page .items th { padding: 6px 6px; font-size: 8.1px; }
        .receipt-page .items td { padding: 7px 6px; }
        .receipt-page .receipt-evidence { margin-top: 10px; }
        .receipt-page .receipt-evidence th { padding: 5px 7px; font-size: 8px; }
        .receipt-page .receipt-evidence td { padding: 6px 7px; }
        .receipt-page .receipt-signature .signature { min-height: 70px; padding: 10px; }
        .receipt-page .signature-table { margin-top: 11px; }
        .receipt-page .sign-line { margin-top: 19px; }
        .receipt-page .footer { left: 36px; right: 36px; bottom: 18px; }
    </style>
</head>
<body>
<div class="page {{ $isGoodsReceipt ? 'receipt-page' : '' }}">
    <div class="masthead">
        <table class="masthead-table">
            <tr>
                <td style="width: 68%;">
                    <table class="brand-table">
                        <tr>
                            <td style="width: 72px;">
                                <div class="logo-frame">
                                    @if($logoPath && file_exists($logoPath))
                                        <img class="logo" src="{{ $logoPath }}" alt="{{ $companyProfile->displayName() }}">
                                    @else
                                        <div class="logo-initials">{{ $companyInitials }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="brand-copy">
                                    <div class="brand-name">{{ $company['name'] }}</div>
                                    @if($company['tagline'])
                                        <div class="brand-tagline">{{ $company['tagline'] }}</div>
                                    @endif
                                    <div class="company-meta">@if($company['reg']) Reg. No. {{ $company['reg'] }}<br>@endif{{ $company['address_inline'] }}@if($company['contact_line'])<br>{{ $company['contact_line'] }}@endif</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="doc-title" style="width: 32%;">
                    <div class="doc-context">{{ $documentContext }}</div>
                    <h1>{{ $title }}</h1>
                    <div class="doc-number"><span>Document No.</span>{{ $document->document_number }}</div>
                </td>
            </tr>
        </table>
        <div class="masthead-rule"></div>
    </div>

    @if($isGoodsReceipt)
        <table class="layout-table">
            <tr>
                <td style="width: 52%;">
                    <div class="section-title">{{ $receiptPartyTitle }}</div>
                    <div class="card accent">
                        <h2>{{ $party?->name ?? 'Supplier' }}</h2>
                        <div class="muted">
                            @if($partyContact) Attn: {{ $partyContact }}<br>@endif
                            {!! nl2br(e($partyAddress ?: '')) !!}
                            @if($partyEmail)<br>Email: {{ $partyEmail }}@endif
                        </div>
                    </div>
                </td>
                <td class="col-gap"></td>
                <td style="width: 48%;">
                    <div class="section-title">{{ $receiptDetailsTitle }}</div>
                    <div class="details">
                        <table class="detail-row">
                            <tr><td class="label">Receipt No.</td><td class="value">{{ $document->document_number }}</td></tr>
                            <tr><td class="label">{{ $receiptKind === 'service' ? 'Accepted Date' : 'Received Date' }}</td><td class="value">{{ optional($document->issue_date)->format('d M Y') }}</td></tr>
                            <tr><td class="label">Source PO</td><td class="value">{{ $document->relatedDocument?->document_number ?? '-' }}</td></tr>
                            <tr><td class="label">{{ $receiptKind === 'service' ? 'Service Report / UAT Ref.' : 'Delivery Order Ref.' }}</td><td class="value">{{ $document->external_reference ?: '-' }}</td></tr>
                        </table>
                    </div>
                    <span class="receipt-status">{{ $document->statusDisplay() }}</span>
                </td>
            </tr>
        </table>

        <div class="receipt-purpose">
            @if($receiptKind === 'service')
                Purpose: records service completion or milestone acceptance against the purchase order before supplier invoice matching.
            @elseif($receiptKind === 'goods')
                Purpose: records goods physically received against the purchase order before supplier invoice matching.
            @else
                Purpose: records received goods and accepted services against the purchase order before supplier invoice matching.
            @endif
        </div>

        <table class="receipt-summary">
            <tr>
                <td style="width: 49%;">
                    <table class="receipt-fact-grid">
                        <tr>
                            <td><span>{{ $receiptKind === 'service' ? 'Accepted For' : 'Received At' }}</span><strong>{{ $projectName }}</strong></td>
                            <td><span>{{ $receiptKind === 'service' ? 'Service Location' : 'Receiving Location' }}</span><strong>{{ $deliveryTo ?: '-' }}</strong></td>
                        </tr>
                        <tr>
                            <td><span>{{ $receiptKind === 'service' ? 'Accepted By' : 'Received By' }}</span><strong>{{ $document->creator?->name ?? $companyProfile->displayName() }}</strong></td>
                            <td><span>Matching Status</span><strong>{{ $document->status === 'received' ? 'Ready for invoice matching' : $document->statusDisplay() }}</strong></td>
                        </tr>
                    </table>
                </td>
                <td class="col-gap"></td>
                <td style="width: 48%;">
                    <div class="receipt-note" style="margin-top: 0; min-height: 91px;">
                        <h3>{{ $receiptKind === 'service' ? 'Acceptance Remarks' : 'Receiving Remarks' }}</h3>
                        <div class="muted">{!! nl2br(e($document->notes ?: ($receiptKind === 'service' ? 'Service delivered and accepted for verification against the purchase order and supplier invoice.' : 'Goods received for verification against the purchase order and supplier invoice.'))) !!}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th style="width: 34px;">No.</th>
                <th>{{ $receiptKind === 'service' ? 'Accepted Service / Deliverable' : ($receiptKind === 'goods' ? 'Received Item' : 'Received / Accepted Item or Service') }}</th>
                <th class="right" style="width: 72px;">{{ $receiptKind === 'service' ? 'PO Qty' : 'Ordered Qty' }}</th>
                <th class="right" style="width: 72px;">{{ $receiptKind === 'service' ? 'Completed Qty' : 'Received Qty' }}</th>
                <th class="right" style="width: 72px;">Accepted Qty</th>
                <th class="right" style="width: 72px;">Exception Qty</th>
                <th style="width: 54px;">Unit</th>
                <th style="width: 100px;">{{ $receiptKind === 'service' ? 'Evidence / Remarks' : 'Condition / Remarks' }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($document->items as $item)
                @php
                    $sourceItem = $sourceItems->values()->get($loop->index);
                    $orderedQty = $sourceItem?->quantity ?? $item->quantity;
                    $exceptionQty = max(0, (float) $orderedQty - (float) $item->quantity);
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><span class="desc">{{ $item->description }}</span></td>
                    <td class="right">{{ \App\Models\Document::formatQuantity($orderedQty) }}</td>
                    <td class="right">{{ \App\Models\Document::formatQuantity($item->quantity) }}</td>
                    <td class="right">{{ \App\Models\Document::formatQuantity($item->quantity) }}</td>
                    <td class="right">{{ \App\Models\Document::formatQuantity($exceptionQty) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $exceptionQty > 0 ? 'Partial / check balance' : 'Accepted' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="receipt-evidence">
            <thead>
            <tr>
                <th style="width: 30%;">Supporting Evidence</th>
                <th>File / Reference</th>
                <th style="width: 24%;">Purpose</th>
            </tr>
            </thead>
            <tbody>
            @forelse($receiptEvidenceAttachments as $attachment)
                <tr>
                    <td>{{ $receiptEvidenceLabels[$attachment->category] ?? str($attachment->category ?: 'supporting_document')->replace('_', ' ')->title() }}</td>
                    <td>{{ $attachment->original_name }}</td>
                    <td>{{ $attachment->isPreviewable() ? 'Preview available' : 'Stored reference' }}</td>
                </tr>
            @empty
                <tr>
                    <td>Evidence pending</td>
                    <td>No delivery order, service report, UAT sign-off, photo, or other receiving evidence has been uploaded.</td>
                    <td>Upload before invoice matching</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="receipt-note">
            <h3>{{ $receiptKind === 'service' ? 'Service Acceptance Declaration' : 'Receiving Declaration' }}</h3>
            <div class="muted">
                This record confirms that the listed {{ $receiptKind === 'service' ? 'services or deliverables have been accepted' : ($receiptKind === 'goods' ? 'goods have been received' : 'goods and services have been received or accepted') }} for the stated project/location. Supplier invoice processing remains subject to invoice verification, purchase order matching, exception review, and payment approval.
            </div>
        </div>

        <table class="signature-table receipt-signature">
            <tr>
                <td style="width: 32%;">
                    <div class="signature">
                        <strong>{{ $receiptKind === 'service' ? 'Accepted By' : 'Received By' }}</strong>
                        <div class="sign-line">Name, signature and date</div>
                    </div>
                </td>
                <td class="tri-gap"></td>
                <td style="width: 32%;">
                    <div class="signature">
                        <strong>{{ $receiptKind === 'service' ? 'Service Verified By' : 'Checked / Inspected By' }}</strong>
                        <div class="sign-line">Name, signature and date</div>
                    </div>
                </td>
                <td class="tri-gap"></td>
                <td style="width: 32%;">
                    <div class="signature">
                        <strong>Approved For Matching</strong>
                        <div class="sign-line">Name, signature and date</div>
                    </div>
                </td>
            </tr>
        </table>
    @else
    @if($isSupplierPo)
        <table class="layout-table">
            <tr>
                <td style="width: 32%;">
                    <div class="section-title">Vendor</div>
                    <div class="card accent">
                        <h2>{{ $party?->name ?? 'Supplier' }}</h2>
                        <div class="muted">{{ $partyContact ?: 'Sales Department' }}<br>{!! nl2br(e($partyAddress ?: '')) !!}<br>{{ $partyEmail ?: '' }}</div>
                    </div>
                </td>
                <td class="tri-gap"></td>
                <td style="width: 32%;">
                    <div class="section-title">Ship To</div>
                    <div class="card">
                        <h2>{{ $projectName }}</h2>
                        <div class="muted">{!! nl2br(e($deliveryTo)) !!}</div>
                    </div>
                </td>
                <td class="tri-gap"></td>
                <td style="width: 32%;">
                    <div class="section-title">Bill To</div>
                    <div class="card">
                        <h2>{{ $companyProfile->displayName() }}</h2>
                        <div class="muted">{{ $company['address'] }}<br>Email: {{ $company['email'] }}<br>Phone: {{ $company['phone'] }}</div>
                    </div>
                </td>
            </tr>
        </table>
    @else
        <table class="layout-table">
            <tr>
                <td style="width: 52%;">
                    <div class="section-title">{{ $isInvoice ? 'Bill To' : ($isQuotation ? 'Quote To' : 'Party') }}</div>
                    <div class="card accent">
                        <h2>{{ $party?->name ?? 'Internal' }}</h2>
                        <div class="muted">
                            @if($partyContact) Attn: {{ $partyContact }}<br>@endif
                            {!! nl2br(e($partyAddress ?: '')) !!}
                            @if($partyEmail)<br>Email: {{ $partyEmail }}@endif
                        </div>
                    </div>
                </td>
                <td class="col-gap"></td>
                <td style="width: 48%;">
                    <div class="section-title">{{ $isInvoice ? 'Invoice Details' : ($isQuotation ? 'Quotation Details' : ($isCustomerPo ? 'Customer PO Details' : 'Document Details')) }}</div>
                    <div class="details">
                        <table class="detail-row">
                            @if($isQuotation)
                                <tr><td class="label">Quotation No.</td><td class="value">{{ $document->document_number }}</td></tr>
                                <tr><td class="label">Quotation Date</td><td class="value">{{ optional($document->issue_date)->format('d M Y') }}</td></tr>
                                <tr><td class="label">Valid Until</td><td class="value">{{ optional($document->due_date)->format('d M Y') ?: '-' }}</td></tr>
                                <tr><td class="label">Customer Ref.</td><td class="value">{{ $document->external_reference ?: '-' }}</td></tr>
                            @elseif($isInvoice)
                                <tr><td class="label">Invoice Date</td><td class="value">{{ optional($document->issue_date)->format('d M Y') }}</td></tr>
                                <tr><td class="label">Due Date</td><td class="value">{{ optional($document->due_date)->format('d M Y') ?: '-' }}</td></tr>
                                <tr><td class="label">PO Ref.</td><td class="value">{{ $relatedRef ?: '-' }}</td></tr>
                                @if($document->progress_invoice_number && $document->progress_invoice_total)
                                    <tr><td class="label">Progress Invoice</td><td class="value">No. {{ $document->progress_invoice_number }} of {{ $document->progress_invoice_total }}</td></tr>
                                @endif
                                @if($document->billing_stage_name)
                                    <tr><td class="label">Billing Stage</td><td class="value">{{ $document->billing_stage_name }}</td></tr>
                                @endif
                                <tr><td class="label">Payment Term</td><td class="value">{{ $document->paymentTermsDisplay() }}</td></tr>
                            @elseif($isCustomerPo)
                                <tr><td class="label">Customer PO No.</td><td class="value">{{ $document->document_number }}</td></tr>
                                <tr><td class="label">Date Received</td><td class="value">{{ optional($document->issue_date)->format('d M Y') }}</td></tr>
                                <tr><td class="label">Completion Target</td><td class="value">{{ optional($document->due_date)->format('d M Y') ?: '-' }}</td></tr>
                                <tr><td class="label">PO Ref.</td><td class="value">{{ $document->external_reference ?: '-' }}</td></tr>
                            @else
                                <tr><td class="label">Document No.</td><td class="value">{{ $document->document_number }}</td></tr>
                                <tr><td class="label">Document Date</td><td class="value">{{ optional($document->issue_date)->format('d M Y') }}</td></tr>
                                <tr><td class="label">Reference</td><td class="value">{{ $document->external_reference ?: '-' }}</td></tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <table class="info-strip">
        <tr>
            @if($isSupplierPo)
                <td><span>PO Date</span><strong>{{ optional($document->issue_date)->format('d M Y') }}</strong></td>
                <td><span>Delivery Date</span><strong>{{ optional($document->due_date)->format('d M Y') ?: '-' }}</strong></td>
                <td><span>Payment Terms</span><strong>{{ $document->paymentTermsDisplay() }}</strong></td>
                <td><span>Supplier Ref.</span><strong>{{ $document->external_reference ?: ($document->relatedDocument?->document_number ?? '-') }}</strong></td>
            @else
                <td><span>Currency</span><strong>{{ $document->currency }}</strong></td>
                <td><span>Payment Terms</span><strong>{{ $document->paymentTermsDisplay() }}</strong></td>
                <td><span>{{ $isInvoice ? 'Reference' : 'Prepared By' }}</span><strong>{{ $isInvoice ? ($relatedRef ?: '-') : $companyProfile->displayName() }}</strong></td>
                <td><span>Site / Project</span><strong>{{ $projectName }}</strong></td>
            @endif
        </tr>
    </table>

    @if($isQuotation && filled($document->notes))
        <div class="terms" style="margin-top: 18px; border: 1px solid #d8e2ee; border-top: 3px solid #1d7df2;">
            <h3>Project Scope Summary</h3>
            <div class="muted">{!! nl2br(e($document->notes)) !!}</div>
        </div>
    @endif

    <table class="items">
        <thead>
        <tr>
            <th style="width: 34px;">No.</th>
            <th>{{ $isSupplierPo ? 'Item / Service Description' : 'Description' }}</th>
            <th class="right" style="width: 50px;">Qty</th>
            <th style="width: 48px;">Unit</th>
            <th class="right" style="width: 82px;">Unit Price</th>
            <th class="right" style="width: 78px;">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><span class="desc">{{ $item->description }}</span></td>
                <td class="right">{{ \App\Models\Document::formatQuantity($item->quantity) }}</td>
                <td>{{ $item->unit }}</td>
                <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($paymentStages->isNotEmpty())
        <div class="schedule">
            <div class="schedule-title">{{ $isInvoice ? 'Progress Billing Summary' : 'Payment Schedule' }}</div>
            <table>
                <thead>
                @if($isInvoice)
                    <tr><th>Billing Stage</th><th class="right">%</th><th class="right">Scheduled Amount</th><th class="right">Previously Invoiced</th><th class="right">This Invoice</th><th class="right">Remaining To Invoice</th></tr>
                @else
                    <tr><th>Billing Stage</th><th>{{ $isSupplierPo ? 'Supplier May Invoice When' : 'Invoice Condition' }}</th><th class="right">%</th><th class="right">Amount</th><th>Payment Term</th></tr>
                @endif
                </thead>
                <tbody>
                @foreach($paymentStages as $stage)
                    <tr class="{{ $stage->is_current ? 'current' : '' }}">
                        @if($isInvoice)
                            <td>{{ $stage->stage_name }}</td>
                            <td class="right">{{ number_format($stage->percentage, 0) }}%</td>
                            <td class="right">{{ $document->currency }} {{ number_format($stage->amount, 2) }}</td>
                            <td class="right">{{ $document->currency }} {{ number_format($stage->previously_invoiced, 2) }}</td>
                            <td class="right">{{ $document->currency }} {{ number_format($stage->current_invoice, 2) }}</td>
                            <td class="right">{{ $document->currency }} {{ number_format($stage->remaining_amount, 2) }}</td>
                        @else
                            <td>{{ $stage->stage_name }}</td>
                            <td>{{ $stage->condition_label ?: '-' }}</td>
                            <td class="right">{{ number_format($stage->percentage, 0) }}%</td>
                            <td class="right">{{ $document->currency }} {{ number_format($stage->amount, 2) }}</td>
                            <td>{{ $stage->payment_term ?: '-' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <table class="lower">
        <tr>
            <td style="width: 51%;">
                @if($isInvoice)
                    <div class="payment">
                        <h3>Payment Details</h3>
                        <div><strong>Payment Method:</strong> Bank transfer<br><strong>Payment Term:</strong> {{ $document->paymentTermsDisplay() }}<br><strong>Payment Reference:</strong> {{ $document->document_number }}<br><strong>Email:</strong> {{ $company['email'] }}</div>
                    </div>
                    <div class="amount-due">
                        <span>Balance Due</span>
                        <strong>{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</strong>
                    </div>
                @else
                    <div class="terms">
                        <h3>{{ $isSupplierPo ? 'Purchase Order Terms' : 'Terms And Conditions' }}</h3>
                        @if($terms)
                            <ul>
                                @foreach($terms as $term)
                                    @if(filled($term))
                                        <li>{{ $term }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @else
                            <div class="muted">No terms stated.</div>
                        @endif
                    </div>
                @endif
            </td>
            <td class="col-gap"></td>
            <td style="width: 46%;">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="right">{{ $document->currency }} {{ number_format($document->subtotal, 2) }}</td></tr>
                    <tr><td>Tax</td><td class="right">{{ $document->currency }} {{ number_format($document->tax_total, 2) }}</td></tr>
                    <tr class="grand"><td>{{ $isSupplierPo ? 'PO Total' : ($isInvoice ? 'Invoice Total' : 'Total') }}</td><td class="right">{{ $document->currency }} {{ number_format($document->total, 2) }}</td></tr>
                    @if($isInvoice)
                        <tr><td>Paid</td><td class="right">{{ $document->currency }} {{ number_format($document->paidAmount(), 2) }}</td></tr>
                        <tr class="due"><td>Balance Due</td><td class="right">{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td style="width: 49%;">
                <div class="signature">
                    <strong>{{ $isSupplierPo ? 'Authorized Signature' : ($isInvoice ? 'Issued By' : 'Prepared By') }}</strong>
                    <div class="sign-line">{{ $companyProfile->displayName() }}</div>
                </div>
            </td>
            <td class="col-gap"></td>
            <td style="width: 49%;">
                <div class="signature">
                    <strong>{{ $isSupplierPo ? 'Supplier Acknowledgement' : ($isInvoice ? 'Received By' : 'Customer Acceptance') }}</strong>
                    <div class="sign-line">Name, signature and company stamp</div>
                </div>
            </td>
        </tr>
    </table>
    @endif

    <div class="footer">
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="border: 0; padding: 0;"><strong>{{ $companyProfile->displayName() }}</strong> | {{ $isSupplierPo ? trim('Reg. No. '.$company['reg'].' | '.$company['email'], ' |') : $company['address'] }}</td>
                <td class="right" style="border: 0; padding: 0;">Page 1 of 1</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>

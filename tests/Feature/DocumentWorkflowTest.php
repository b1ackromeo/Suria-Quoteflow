<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\AttachmentExtraction;
use App\Models\AuditTrail;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Documents\BusinessDocumentCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Supplier $supplier;

    private Product $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Trading Sdn Bhd',
            'code' => 'ACME',
            'email' => 'accounts@acme.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Best Supplies Sdn Bhd',
            'code' => 'BEST',
            'category' => 'Materials / Hardware',
            'email' => 'accounts@best.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $this->service = Product::create([
            'type' => 'service',
            'sku' => 'CONSULT',
            'name' => 'Consulting package',
            'description' => 'Consulting support, implementation planning, testing, and handover documentation.',
            'unit' => 'job',
            'selling_price' => 1200,
            'cost_price' => 450,
            'tax_rate' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_product_library_shows_document_wording_without_internal_cost(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Products and services');
        $response->assertSee('Consulting support, implementation planning, testing, and handover documentation.');
        $response->assertSee('Default document price');
        $response->assertDontSee('Internal cost');
        $response->assertDontSee('450.00');
    }

    public function test_user_access_page_uses_full_workspace_and_filters_accounts(): void
    {
        User::factory()->create([
            'name' => 'QuoteFlow Sales User',
            'email' => 'sales-ui@quoteflow.test',
            'role' => 'sales',
            'is_active' => true,
        ]);

        $response = $this->get(route('users.index', ['q' => 'sales-ui', 'role' => 'sales', 'status' => 'active']));

        $response->assertOk();
        $response->assertSee('Access control');
        $response->assertSee('Users and permissions');
        $response->assertSee('sales-ui@quoteflow.test');
        $response->assertSee('Customer quotations, customer PO records, and customer invoices.');
        $response->assertDontSee('<section class="panel">', false);
    }

    public function test_supplier_directory_and_form_use_procurement_workspace(): void
    {
        $this->supplier->update([
            'category' => 'Materials / Hardware',
            'contact_person' => 'Nur Procurement',
            'phone' => '+60 12-345 6789',
            'address' => "Level 12, Menara Supply\nKuala Lumpur 50000\nMalaysia",
        ]);

        Supplier::create([
            'name' => 'Inactive Supplier Sdn Bhd',
            'code' => 'INACTIVE',
            'category' => 'Office / Admin',
            'email' => 'inactive@example.com',
            'payment_terms_days' => 45,
            'is_active' => false,
        ]);

        $response = $this->get(route('suppliers.index', ['q' => 'Best', 'status' => 'active', 'category' => 'Materials / Hardware']));

        $response->assertOk();
        $response->assertSee('Supplier directory');
        $response->assertSee('Supplier records');
        $response->assertSee('Best Supplies Sdn Bhd');
        $response->assertSee('Materials / Hardware');
        $response->assertSee('Procurement contact');
        $response->assertSee('30 days after invoice');
        $response->assertDontSee('vendor contacts');
        $response->assertDontSee('Supplier List');
        $response->assertDontSee('directory-side-panel');
        $response->assertDontSee('Inactive Supplier Sdn Bhd');

        $form = $this->get(route('suppliers.create'));

        $form->assertOk();
        $form->assertSee('Supplier identity');
        $form->assertSee('Supplier category');
        $form->assertSee('Address line 1');
        $form->assertSee('Supplier preview');
        $form->assertSee('Default payment term');
        $form->assertSee('data-address-output="supplier"', false);
        $form->assertDontSee('class="form-input min-h-28" name="address"', false);
    }

    public function test_document_numbers_resume_from_existing_documents_when_sequence_is_missing(): void
    {
        $year = now()->format('Y');

        Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-'.$year.'-00012',
            'external_reference' => 'SEQ-CHECK',
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'currency' => 'MYR',
            'created_by' => $this->admin->id,
        ]);

        $this->assertSame('CQ-'.$year.'-00013', Document::nextNumber('customer_quotation'));
        $this->assertDatabaseHas('document_sequences', [
            'type' => 'customer_quotation',
            'year' => (int) $year,
            'last_number' => 13,
        ]);
    }

    public function test_outgoing_customer_workflow_runs_from_quotation_to_closed_invoice(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TEST-OUT-Q',
            'description' => 'Customer inquiry consulting package',
            'quantity' => 2,
            'unit_price' => 1200,
        ]);

        $this->submitAndApprove($quotation);
        $this->assertSame('approved', $quotation->refresh()->status);

        $customerPo = $this->createDocument('customer-pos', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $quotation->id,
            'external_reference' => 'TEST-OUT-CPO',
            'description' => 'Customer PO services',
            'quantity' => 2,
            'unit_price' => 1200,
        ]);

        $this->submitAndApprove($customerPo);
        $this->transition($customerPo, 'issue', 'issued');
        $this->transition($customerPo, 'fulfill', 'fulfilled');

        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $customerPo->id,
            'external_reference' => 'TEST-OUT-INV',
            'description' => 'Invoice for completed services',
            'quantity' => 2,
            'unit_price' => 1200,
        ]);

        $this->submitAndApprove($invoice);
        $this->transition($invoice, 'issue', 'issued');

        $invoiceActions = $this->get(route('documents.show', $invoice));
        $invoiceActions->assertOk();
        $invoiceActions->assertSee('Record payment');
        $invoiceActions->assertDontSee('Delivery / service complete');
        $invoiceActions->assertDontSee('<button type="submit" class="btn btn-secondary w-full">Close</button>', false);

        $this->post(route('documents.transition', [$invoice, 'fulfill']))
            ->assertStatus(422);
        $this->post(route('documents.transition', [$invoice, 'close']))
            ->assertStatus(422);

        $this->recordPayment($invoice, 'incoming');

        $paidInvoiceActions = $this->get(route('documents.show', $invoice));
        $paidInvoiceActions->assertOk();
        $paidInvoiceActions->assertSee('<button type="submit" class="btn btn-secondary w-full">Close</button>', false);

        $this->transition($invoice, 'close', 'closed');

        $this->assertSame($quotation->id, $customerPo->refresh()->related_document_id);
        $this->assertSame($customerPo->id, $invoice->refresh()->related_document_id);
        $this->assertSame('2592.00', $invoice->total);
        $this->assertDatabaseHas('payments', [
            'document_id' => $invoice->id,
            'direction' => 'incoming',
            'amount' => '2592.00',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'document_close',
            'auditable_type' => Document::class,
            'auditable_id' => $invoice->id,
        ]);
    }

    public function test_customer_documents_can_create_next_linked_drafts_from_command_center(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CONVERT-Q-001',
            'description' => 'Converted customer quotation line',
            'quantity' => 2,
            'unit_price' => 1200,
        ]);

        $this->submitAndApprove($quotation);

        $quotationShow = $this->get(route('documents.show', $quotation));
        $quotationShow->assertOk();
        $quotationShow->assertSee('Create customer PO received');

        $this->post(route('documents.convert', [$quotation, 'customer-pos']))
            ->assertRedirect();

        $customerPo = Document::query()
            ->where('type', 'customer_po')
            ->where('related_document_id', $quotation->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('draft', $customerPo->status);
        $this->assertSame('quotation', $customerPo->source_type);
        $this->assertNull($customerPo->external_reference);
        $this->assertSame($this->customer->id, $customerPo->customer_id);
        $this->assertSame('2592.00', $customerPo->total);
        $this->assertSame('Converted customer quotation line', $customerPo->items()->firstOrFail()->description);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'document_converted',
            'auditable_type' => Document::class,
            'auditable_id' => $customerPo->id,
        ]);

        $this->post(route('documents.convert', [$customerPo, 'customer-invoices']))
            ->assertStatus(422);

        $this->submitAndApprove($customerPo);
        $this->transition($customerPo, 'issue', 'issued');
        $this->transition($customerPo, 'fulfill', 'fulfilled');

        $poShow = $this->get(route('documents.show', $customerPo));
        $poShow->assertOk();
        $poShow->assertSee('Create customer invoice');

        $this->post(route('documents.convert', [$customerPo, 'customer-invoices']))
            ->assertRedirect();

        $invoice = Document::query()
            ->where('type', 'customer_invoice')
            ->where('related_document_id', $customerPo->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('draft', $invoice->status);
        $this->assertSame('customer_po', $invoice->source_type);
        $this->assertSame($customerPo->document_number, $invoice->external_reference);
        $this->assertSame($this->customer->id, $invoice->customer_id);
        $this->assertSame('2592.00', $invoice->total);
        $this->assertNull($invoice->due_date);
        $this->assertSame('Converted customer quotation line', $invoice->items()->firstOrFail()->description);
    }

    public function test_incoming_supplier_workflow_runs_from_purchase_request_to_closed_supplier_invoice(): void
    {
        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'TEST-IN-PR',
            'description' => 'Internal purchase request',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $this->submitAndApprove($purchaseRequest);

        $supplierQuotation = $this->createDocument('supplier-quotations', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $purchaseRequest->id,
            'external_reference' => 'TEST-IN-SQ',
            'description' => 'Supplier quotation for requested items',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $this->submitAndApprove($supplierQuotation);

        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierQuotation->id,
            'external_reference' => 'TEST-IN-SPO',
            'description' => 'Purchase order issued to vendor',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $goodsReceipt = $this->createDocument('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'external_reference' => 'TEST-IN-GR',
            'description' => 'Goods received from supplier',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $this->submitAndApprove($goodsReceipt);
        $this->transition($goodsReceipt, 'receive', 'received');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $goodsReceipt->id,
            'external_reference' => 'TEST-IN-SIN',
            'description' => 'Supplier invoice matched to receipt',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $this->uploadAndVerifySupplierInvoiceExtraction($supplierInvoice, [
            'invoice_number' => 'TEST-IN-SIN',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '2250.00',
            'tax_total' => '180.00',
            'total' => '2430.00',
            'payment_terms' => '30 days from invoice date',
        ]);

        $this->submitAndApprove($supplierInvoice);
        $this->transition($supplierInvoice, 'match', 'matched');
        $this->recordPayment($supplierInvoice, 'outgoing');
        $this->transition($supplierInvoice, 'close', 'closed');

        $this->assertSame($purchaseRequest->id, $supplierQuotation->refresh()->related_document_id);
        $this->assertSame($supplierQuotation->id, $supplierPo->refresh()->related_document_id);
        $this->assertSame($supplierPo->id, $goodsReceipt->refresh()->related_document_id);
        $this->assertSame($goodsReceipt->id, $supplierInvoice->refresh()->related_document_id);
        $this->assertSame('2430.00', $supplierInvoice->total);
        $this->assertDatabaseHas('payments', [
            'document_id' => $supplierInvoice->id,
            'direction' => 'outgoing',
            'amount' => '2430.00',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'document_match',
            'auditable_type' => Document::class,
            'auditable_id' => $supplierInvoice->id,
        ]);
    }

    public function test_global_pending_approvals_page_lists_pending_documents_across_modules(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'GLOBAL-PENDING-CQ',
            'description' => 'Quotation waiting for global approval list',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->submitForApproval($quotation);

        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'GLOBAL-PENDING-PR',
            'description' => 'Purchase request waiting for global approval list',
            'quantity' => 2,
            'unit_price' => 450,
        ]);
        $this->submitForApproval($purchaseRequest);

        $response = $this->get(route('approvals.pending'));

        $response->assertOk();
        $response->assertSee('Pending approvals');
        $response->assertSee($quotation->document_number);
        $response->assertSee('Customer quotation');
        $response->assertSee($this->customer->name);
        $response->assertSee($purchaseRequest->document_number);
        $response->assertSee('Purchase request');
        $response->assertSee($this->supplier->name);
        $response->assertSee($this->admin->name);
        $response->assertSee('Pending Approval');
        $response->assertSee(route('documents.show', $quotation), false);
        $response->assertSee(route('documents.show', $purchaseRequest), false);
    }

    public function test_global_header_does_not_show_date_range_or_approval_bell(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'GLOBAL-HEADER-CQ',
            'description' => 'Quotation waiting for approval without global header shortcut',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->submitForApproval($quotation);

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertDontSee('date-control', false);
        $response->assertDontSee('notification-button', false);
        $response->assertDontSee('aria-label="Pending approvals"', false);
        $response->assertDontSee(now()->startOfMonth()->format('d M Y').' - '.now()->format('d M Y'));
    }

    public function test_dashboard_starts_with_operations_today_and_global_task_links(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TODAY-WORK-CQ',
            'description' => 'Quotation counted in today work',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->submitForApproval($quotation);

        $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Direct supplier invoice waiting for verification in dashboard.',
            'external_reference' => 'TODAY-WORK-SIN',
            'description' => 'Supplier invoice waiting for verification',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Operations today');
        $response->assertSee('Open work by urgency.');
        $response->assertDontSee('dashboard-title-snapshot', false);
        $response->assertSee('Needs attention');
        $response->assertSee('Each card opens the records behind it.');
        $response->assertSee('Pending approvals');
        $response->assertSee('Verify supplier invoices');
        $response->assertSee('Match supplier invoices');
        $response->assertSee('Overdue receivables');
        $response->assertSee('Quick create');
        $response->assertSee('Customer quotation');
        $response->assertSee('Financial exposure');
        $response->assertSee('Invoice aging');
        $response->assertSee('Sales and purchasing');
        $response->assertSee('Customer sales');
        $response->assertSee('Customer quotation to payment');
        $response->assertSee('Open sales records');
        $response->assertSee('Customer POs received');
        $response->assertSee('Customer invoices');
        $response->assertSee('Supplier purchasing');
        $response->assertSee('Purchase request to supplier payment');
        $response->assertSee('Open purchasing records');
        $response->assertSee('Purchase orders');
        $response->assertSee('6-month movement');
        $response->assertSee('Invoices issued and payments recorded.');
        $response->assertSee('Customer invoices');
        $response->assertSee('Supplier invoices');
        $response->assertSee('Customer payments received');
        $response->assertSee('Supplier payments made');
        $response->assertSee('Open reports');
        $response->assertSee('href="'.route('approvals.pending').'"', false);
        $response->assertSee('brand/suria-quoteflow-horizontal-lockup.svg', false);
        $response->assertSee('brand/favicon.svg', false);
        $response->assertSee('sidebar-workspace', false);
        $response->assertSee('Current company');
        $response->assertSee('sidebar-account-panel', false);
        $response->assertSee('mobile-task-strip', false);
        $response->assertSee('aria-label="My work shortcuts"', false);
        $response->assertSee('Tasks ready for action');
        $response->assertSee('Sales, purchasing, directory');
        $response->assertSee($this->admin->name);
        $response->assertSee('Logout');
        $response->assertDontSee('date-control', false);
        $response->assertDontSee('notification-button', false);
        $response->assertDontSee('New quotation');
        $response->assertDontSee('Recent activity');
        $response->assertDontSee('Approval queue');
        $response->assertDontSee('Approval overview');
        $response->assertDontSee('Top customers');
        $response->assertDontSee('company-identity-card', false);
        $response->assertDontSee('company-identity-pill', false);
        $response->assertDontSee('user-chip', false);
        $response->assertDontSee('Operations dashboard');
        $response->assertDontSee('Workflow lanes');
        $response->assertDontSee('Outgoing workflow');
        $response->assertDontSee('Incoming workflow');
        $response->assertDontSee('View outgoing');
        $response->assertDontSee('View incoming');
        $response->assertDontSee('Outgoing revenue');
        $response->assertDontSee('Incoming procurement');
        $response->assertDontSee('Active workspace');
        $response->assertDontSee('>Exports</span>', false);
    }

    public function test_login_uses_approved_product_logo(): void
    {
        auth()->logout();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Suria QuoteFlow');
        $response->assertSee('brand/SuriaQuoteflowlogo.svg', false);
        $response->assertSee('brand/favicon.svg', false);
    }

    public function test_non_approver_cannot_approve_pending_document_through_direct_post(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'GLOBAL-NON-APPROVER-CQ',
            'description' => 'Quotation should reject sales approval attempt',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->submitForApproval($quotation);

        $salesUser = User::factory()->create([
            'role' => 'sales',
            'is_active' => true,
        ]);

        $this->actingAs($salesUser)
            ->post(route('documents.approve', $quotation), [
                'comment' => 'Sales should not be allowed to approve.',
            ])
            ->assertForbidden();

        $this->assertSame('pending_approval', $quotation->refresh()->status);
        $this->assertDatabaseHas('approvals', [
            'document_id' => $quotation->id,
            'status' => 'pending',
        ]);
    }

    public function test_pending_approvals_page_is_paginated(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->createPendingApprovalRecord('PAGE-PENDING-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), now()->addMinutes($i));
        }

        $response = $this->get(route('approvals.pending'));

        $response->assertOk();
        $response->assertViewHas('approvals', function ($approvals) {
            return $approvals instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                && $approvals->perPage() === 25
                && $approvals->count() === 25
                && $approvals->total() === 26;
        });
        $response->assertSee('PAGE-PENDING-26');
        $response->assertDontSee('PAGE-PENDING-01');
    }

    public function test_customer_invoice_payment_is_available_only_after_issue_and_part_payment(): void
    {
        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'PAY-ELIG-CUST-DRAFT',
            'description' => 'Customer invoice blocked before issue',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $draftPage = $this->get(route('documents.show', $invoice));
        $draftPage->assertOk();
        $draftPage->assertDontSee('Record payment');
        $draftPage->assertSee('Payment is available after this customer invoice is issued.');

        $this->from(route('documents.show', $invoice))
            ->get(route('payments.create', $invoice))
            ->assertRedirect(route('documents.show', $invoice))
            ->assertSessionHasErrors(['payment' => 'Payment is available after this customer invoice is issued.']);

        $this->from(route('documents.show', $invoice))
            ->post(route('payments.store', $invoice), $this->paymentPayload($invoice, 100, 'PAY-CUST-DRAFT'))
            ->assertRedirect(route('documents.show', $invoice))
            ->assertSessionHasErrors(['payment' => 'Payment is available after this customer invoice is issued.']);

        $this->assertDatabaseMissing('payments', [
            'document_id' => $invoice->id,
            'reference' => 'PAY-CUST-DRAFT',
        ]);

        $this->submitAndApprove($invoice);

        $approvedPage = $this->get(route('documents.show', $invoice));
        $approvedPage->assertOk();
        $approvedPage->assertDontSee('Record payment');
        $approvedPage->assertSee('Payment is available after this customer invoice is issued.');

        $this->from(route('documents.show', $invoice))
            ->post(route('payments.store', $invoice), $this->paymentPayload($invoice, 100, 'PAY-CUST-APPROVED'))
            ->assertRedirect(route('documents.show', $invoice))
            ->assertSessionHasErrors(['payment' => 'Payment is available after this customer invoice is issued.']);

        $this->assertDatabaseMissing('payments', [
            'document_id' => $invoice->id,
            'reference' => 'PAY-CUST-APPROVED',
        ]);

        $this->transition($invoice, 'issue', 'issued');

        $issuedPage = $this->get(route('documents.show', $invoice));
        $issuedPage->assertOk();
        $issuedPage->assertSee('Record payment');

        $this->post(route('payments.store', $invoice), $this->paymentPayload($invoice, 100, 'PAY-CUST-ISSUED'))
            ->assertRedirect();

        $this->assertSame('part_paid', $invoice->refresh()->status);
        $this->assertDatabaseHas('payments', [
            'document_id' => $invoice->id,
            'direction' => 'incoming',
            'reference' => 'PAY-CUST-ISSUED',
            'amount' => '100.00',
        ]);

        $partPaidPage = $this->get(route('documents.show', $invoice));
        $partPaidPage->assertOk();
        $partPaidPage->assertSee('Record payment');

        $this->post(route('payments.store', $invoice), $this->paymentPayload($invoice, 50, 'PAY-CUST-PART-PAID'))
            ->assertRedirect();

        $this->assertSame('part_paid', $invoice->refresh()->status);
        $this->assertDatabaseHas('payments', [
            'document_id' => $invoice->id,
            'direction' => 'incoming',
            'reference' => 'PAY-CUST-PART-PAID',
            'amount' => '50.00',
        ]);
    }

    public function test_document_show_surfaces_command_center_readiness_before_history(): void
    {
        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'COMMAND-CENTER-INV',
            'description' => 'Invoice used to check command center hierarchy',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $response = $this->get(route('documents.show', $invoice));

        $response->assertOk();
        $response->assertSee('Next action');
        $response->assertSee('Required before continuing');
        $response->assertSee('Payment not available yet');
        $response->assertSee('Available actions');
        $response->assertSee('Document details');
        $response->assertSee('Document progress');
        $response->assertSee('Evidence and attachments');
        $response->assertSee('Approval history');
        $response->assertSee('aria-label="Status: Draft. Draft status."', false);
        $response->assertSee('data-status-group="Draft status"', false);
        $response->assertSee('data-label="Description"', false);
        $response->assertSee('data-label="Total"', false);
    }

    public function test_document_status_semantic_groups_distinguish_business_states(): void
    {
        $groups = [
            'draft' => 'Draft status',
            'pending_approval' => 'Waiting for action',
            'approved' => 'Ready for next step',
            'issued' => 'External movement',
            'received' => 'External movement',
            'matched' => 'Control passed',
            'part_paid' => 'Payment in progress',
            'paid' => 'Payment complete',
            'cancelled' => 'Stopped',
            'closed' => 'Final',
        ];

        foreach ($groups as $status => $group) {
            $document = new Document([
                'type' => 'supplier_invoice',
                'status' => $status,
            ]);

            $this->assertSame($group, $document->statusSemanticGroupDisplay());
            $this->assertStringContainsString($group, $document->statusAriaLabel());
        }
    }

    public function test_document_progress_uses_generic_steps_when_no_linked_chain_exists(): void
    {
        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'GENERIC-TIMELINE-INV',
            'description' => 'Standalone invoice uses generic document progress',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $response = $this->get(route('documents.show', $invoice));

        $response->assertOk();
        $response->assertSee('Document progress');
        $response->assertSee('Customer PO received');
        $response->assertSee('Payment');
        $response->assertDontSee('data-document-chain-timeline', false);
    }

    public function test_document_progress_shows_linked_upstream_chain(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CHAIN-CQ',
            'description' => 'Linked customer quotation',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $customerPo = $this->createDocument('customer-pos', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $quotation->id,
            'external_reference' => 'CHAIN-CPO',
            'description' => 'Linked customer PO',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $customerPo->id,
            'external_reference' => 'CHAIN-INV',
            'description' => 'Linked customer invoice',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $response = $this->get(route('documents.show', $invoice));
        $content = $response->getContent();
        $timelineStart = strpos($content, 'data-document-chain-timeline');
        $this->assertNotFalse($timelineStart);
        $timelineHtml = substr($content, $timelineStart);

        $response->assertOk();
        $response->assertSee('data-document-chain-timeline', false);
        $response->assertSee('href="'.route('documents.show', $quotation).'"', false);
        $response->assertSee('href="'.route('documents.show', $customerPo).'"', false);
        $response->assertSee('href="'.route('documents.show', $invoice).'"', false);

        $quotationPosition = strpos($timelineHtml, $quotation->document_number);
        $customerPoPosition = strpos($timelineHtml, $customerPo->document_number);
        $invoicePosition = strpos($timelineHtml, $invoice->document_number);

        $this->assertNotFalse($quotationPosition);
        $this->assertNotFalse($customerPoPosition);
        $this->assertNotFalse($invoicePosition);
        $this->assertLessThan($customerPoPosition, $quotationPosition);
        $this->assertLessThan($invoicePosition, $customerPoPosition);
        $this->assertStringContainsString('Customer quotation - Draft', $timelineHtml);
        $this->assertStringContainsString('Customer PO received - Draft', $timelineHtml);
        $this->assertStringContainsString('Customer invoice - Draft', $timelineHtml);
    }

    public function test_document_progress_chain_traversal_is_bounded_when_links_loop(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CHAIN-LOOP-CQ',
            'description' => 'Quotation with looped related document',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $customerPo = $this->createDocument('customer-pos', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $quotation->id,
            'external_reference' => 'CHAIN-LOOP-CPO',
            'description' => 'Customer PO with looped related document',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $quotation->update(['related_document_id' => $customerPo->id]);

        $response = $this->get(route('documents.show', $quotation));
        $content = $response->getContent();
        $timelineStart = strpos($content, 'data-document-chain-timeline');
        $this->assertNotFalse($timelineStart);
        $timelineHtml = substr($content, $timelineStart);
        $timelineEnd = strpos($timelineHtml, 'data-document-chain-limited');
        $chainHtml = substr($timelineHtml, 0, $timelineEnd === false ? null : $timelineEnd);

        $response->assertOk();
        $response->assertSee('data-document-chain-limited', false);
        $response->assertSee('Only the nearest linked records are shown.');
        $this->assertSame(1, substr_count($chainHtml, $quotation->document_number));
        $this->assertSame(1, substr_count($chainHtml, $customerPo->document_number));
    }

    public function test_pending_approval_show_focuses_on_the_decision_without_duplicate_blocker(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'APPROVAL-DECISION-QUOTE',
            'description' => 'Quotation used to check approval review layout',
            'quantity' => 1,
            'unit_price' => 3000,
        ]);
        $this->submitForApproval($quotation);

        $response = $this->get(route('documents.show', $quotation));

        $response->assertOk();
        $response->assertSee('Next action');
        $response->assertSee('Approval decision');
        $response->assertSee('Approval note');
        $response->assertSee('Rejection reason');
        $response->assertDontSee('Document details');
        $response->assertDontSee('Document progress');
        $response->assertDontSee('Evidence and attachments');
        $response->assertDontSee('Line items');
        $response->assertDontSee('Commercial notes');
        $response->assertDontSee('No payments recorded.');
        $response->assertDontSee('No approval events.');
        $response->assertDontSee('Required before continuing');
        $response->assertDontSee('Approval waiting');
    }

    public function test_admin_can_cancel_allowed_statuses_with_reason_and_audit_payload(): void
    {
        foreach (Document::CANCELLABLE_STATUSES as $status) {
            $quotation = $this->createDocument('customer-quotations', [
                'customer_id' => $this->customer->id,
                'external_reference' => 'CANCEL-ALLOWED-'.strtoupper($status),
                'description' => 'Quotation cancelled from '.$status,
                'quantity' => 1,
                'unit_price' => 1200,
            ]);
            $quotation->update(['status' => $status]);
            $reason = 'Customer stopped this document while it was '.$status.'.';

            $show = $this->get(route('documents.show', $quotation));
            $show->assertOk();
            $show->assertSee('Cancellation reason');
            $show->assertSee('Cancel document');

            $this->from(route('documents.show', $quotation))
                ->post(route('documents.transition', [$quotation, 'cancel']), [
                    'cancellation_reason' => $reason,
                ])
                ->assertRedirect(route('documents.show', $quotation))
                ->assertSessionHas('status', 'Document cancelled.');

            $this->assertSame('cancelled', $quotation->refresh()->status);

            $audit = AuditTrail::where('action', 'document_cancel')
                ->where('auditable_type', Document::class)
                ->where('auditable_id', $quotation->id)
                ->latest('id')
                ->firstOrFail();

            $this->assertSame($this->admin->id, $audit->user_id);
            $this->assertSame('cancelled', $audit->after_values['status'] ?? null);
            $this->assertSame($reason, $audit->after_values['cancellation_reason'] ?? null);
        }
    }

    public function test_cancellation_without_reason_fails(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CANCEL-REASON-REQUIRED',
            'description' => 'Quotation cancellation requires a reason',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $this->from(route('documents.show', $quotation))
            ->post(route('documents.transition', [$quotation, 'cancel']))
            ->assertRedirect(route('documents.show', $quotation))
            ->assertSessionHasErrors([
                'cancellation_reason' => 'Add a cancellation reason before cancelling this document.',
            ]);

        $this->assertSame('draft', $quotation->refresh()->status);
        $this->assertDatabaseMissing('audit_trails', [
            'action' => 'document_cancel',
            'auditable_type' => Document::class,
            'auditable_id' => $quotation->id,
        ]);
    }

    public function test_disallowed_statuses_cannot_be_cancelled(): void
    {
        $blockedStatuses = [
            'pending_approval',
            'fulfilled',
            'received',
            'matched',
            'part_paid',
            'paid',
            'closed',
            'cancelled',
        ];

        foreach ($blockedStatuses as $status) {
            $quotation = $this->createDocument('customer-quotations', [
                'customer_id' => $this->customer->id,
                'external_reference' => 'CANCEL-BLOCKED-'.strtoupper($status),
                'description' => 'Quotation cannot be cancelled from '.$status,
                'quantity' => 1,
                'unit_price' => 1200,
            ]);
            $quotation->update(['status' => $status]);

            $show = $this->get(route('documents.show', $quotation));
            $show->assertOk();
            $show->assertDontSee('Cancel document');

            $this->post(route('documents.transition', [$quotation, 'cancel']), [
                'cancellation_reason' => 'Attempted cancellation from blocked status.',
            ])->assertStatus(422);

            $this->assertSame($status, $quotation->refresh()->status);
            $this->assertDatabaseMissing('audit_trails', [
                'action' => 'document_cancel',
                'auditable_type' => Document::class,
                'auditable_id' => $quotation->id,
            ]);
        }
    }

    public function test_non_manager_write_user_cannot_cancel_document(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CANCEL-NON-MANAGER',
            'description' => 'Quotation cancellation is manager controlled',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $salesUser = User::factory()->create([
            'role' => 'sales',
            'is_active' => true,
        ]);

        $this->actingAs($salesUser);

        $show = $this->get(route('documents.show', $quotation));
        $show->assertOk();
        $show->assertDontSee('Cancel document');

        $this->post(route('documents.transition', [$quotation, 'cancel']), [
            'cancellation_reason' => 'Sales user tried to cancel this quotation.',
        ])->assertForbidden();

        $this->assertSame('draft', $quotation->refresh()->status);
    }

    public function test_cancelled_document_cannot_be_edited_or_transitioned_further(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CANCEL-NO-FURTHER-ACTION',
            'description' => 'Cancelled quotation cannot continue',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $this->from(route('documents.show', $quotation))
            ->post(route('documents.transition', [$quotation, 'cancel']), [
                'cancellation_reason' => 'Customer confirmed the request should stop.',
            ])
            ->assertRedirect(route('documents.show', $quotation));

        $this->assertSame('cancelled', $quotation->refresh()->status);

        $this->get(route('documents.edit', $quotation))
            ->assertStatus(422);

        $this->post(route('documents.transition', [$quotation, 'issue']))
            ->assertStatus(422);

        $this->assertSame('cancelled', $quotation->refresh()->status);
    }

    public function test_supplier_invoice_payment_is_available_only_after_matching(): void
    {
        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Finance approved this low-value direct supplier invoice for payment workflow testing.',
            'external_reference' => 'PAY-ELIG-SIN',
            'description' => 'Supplier invoice blocked before matching',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->uploadAndVerifySupplierInvoiceExtraction($supplierInvoice, [
            'invoice_number' => 'PAY-ELIG-SIN',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '450.00',
            'tax_total' => '36.00',
            'total' => '486.00',
            'payment_terms' => '30 days from invoice date',
        ]);
        $this->submitAndApprove($supplierInvoice);

        $approvedPage = $this->get(route('documents.show', $supplierInvoice));
        $approvedPage->assertOk();
        $approvedPage->assertDontSee('Record payment');
        $approvedPage->assertSee('Match this supplier invoice before recording payment.');

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('payments.store', $supplierInvoice), $this->paymentPayload($supplierInvoice, 100, 'PAY-SIN-APPROVED'))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors(['payment' => 'Match this supplier invoice before recording payment.']);

        $this->assertDatabaseMissing('payments', [
            'document_id' => $supplierInvoice->id,
            'reference' => 'PAY-SIN-APPROVED',
        ]);

        $this->transition($supplierInvoice, 'match', 'matched');

        $matchedPage = $this->get(route('documents.show', $supplierInvoice));
        $matchedPage->assertOk();
        $matchedPage->assertSee('Record payment');

        $this->post(route('payments.store', $supplierInvoice), $this->paymentPayload($supplierInvoice, 100, 'PAY-SIN-MATCHED'))
            ->assertRedirect();

        $this->assertSame('part_paid', $supplierInvoice->refresh()->status);
        $this->assertDatabaseHas('payments', [
            'document_id' => $supplierInvoice->id,
            'direction' => 'outgoing',
            'reference' => 'PAY-SIN-MATCHED',
            'amount' => '100.00',
        ]);
    }

    public function test_incoming_procurement_open_pages_do_not_offer_wrong_issue_actions(): void
    {
        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'NO-ISSUE-PR',
            'description' => 'Internal purchase request should not be issued',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($purchaseRequest);

        $purchaseRequestPage = $this->get(route('documents.show', $purchaseRequest));
        $purchaseRequestPage->assertOk();
        $purchaseRequestPage->assertSee('Use this approved purchase request to request supplier quotations or prepare a purchase order.');
        $purchaseRequestPage->assertSee('Add supplier quotation');
        $purchaseRequestPage->assertSee('Create purchase order');
        $purchaseRequestPage->assertDontSee('Issue the approved document to the customer or supplier.');
        $purchaseRequestPage->assertDontSee('Mark issued');
        $this->post(route('documents.transition', [$purchaseRequest, 'issue']))
            ->assertStatus(422);
        $this->post(route('documents.transition', [$purchaseRequest, 'close']))
            ->assertStatus(422);

        $supplierQuotePrefill = $this->get(route('documents.create', [
            'module' => 'supplier-quotations',
            'source_document_id' => $purchaseRequest->id,
        ]));
        $supplierQuotePrefill->assertOk();
        $supplierQuotePrefill->assertSee('Purchase request');
        $supplierQuotePrefill->assertSee('value="purchase_request"', false);
        $supplierQuotePrefill->assertSee($purchaseRequest->document_number);
        $supplierQuotePrefill->assertSee('Internal purchase request should not be issued');

        $supplierQuotation = $this->createDocument('supplier-quotations', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $purchaseRequest->id,
            'external_reference' => 'NO-ISSUE-SQ',
            'description' => 'Incoming supplier quotation should become PO source',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($supplierQuotation);

        $supplierQuotePage = $this->get(route('documents.show', $supplierQuotation));
        $supplierQuotePage->assertOk();
        $supplierQuotePage->assertSee('Use the accepted supplier quotation to prepare the purchase order.');
        $supplierQuotePage->assertSee('Create purchase order');
        $supplierQuotePage->assertDontSee('Issue quotation');
        $this->post(route('documents.transition', [$supplierQuotation, 'issue']))
            ->assertStatus(422);
        $this->post(route('documents.transition', [$supplierQuotation, 'close']))
            ->assertStatus(422);

        $supplierPoPrefill = $this->get(route('documents.create', [
            'module' => 'supplier-pos',
            'source_document_id' => $supplierQuotation->id,
        ]));
        $supplierPoPrefill->assertOk();
        $supplierPoPrefill->assertSee('Supplier quotation');
        $supplierPoPrefill->assertSee('value="supplier_quote"', false);
        $supplierPoPrefill->assertSee($supplierQuotation->document_number);
        $supplierPoPrefill->assertSee('Incoming supplier quotation should become PO source');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Direct supplier invoice used to check incoming invoice actions.',
            'external_reference' => 'NO-ISSUE-SIN',
            'description' => 'Incoming supplier invoice should be matched not issued',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->uploadAndVerifySupplierInvoiceExtraction($supplierInvoice, [
            'invoice_number' => 'NO-ISSUE-SIN',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '450.00',
            'tax_total' => '36.00',
            'total' => '486.00',
            'payment_terms' => '30 days from invoice date',
        ]);
        $this->submitAndApprove($supplierInvoice);

        $supplierInvoicePage = $this->get(route('documents.show', $supplierInvoice));
        $supplierInvoicePage->assertOk();
        $supplierInvoicePage->assertSee('Match this supplier invoice against the purchase order, receipt, and verified invoice details before payment.');
        $supplierInvoicePage->assertSee('>Match supplier invoice</button>', false);
        $supplierInvoicePage->assertDontSee('Issue invoice');
        $this->post(route('documents.transition', [$supplierInvoice, 'issue']))
            ->assertStatus(422);
    }

    public function test_customer_po_can_be_created_directly_without_quotation(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'source_type' => 'direct_customer_po',
            'source_note' => 'Customer sent PO directly under an agreed rate card.',
            'external_reference' => 'DIRECT-CPO-001',
            'project_name' => 'Cyberjaya Site',
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Direct customer PO service package',
                    'quantity' => 1,
                    'unit' => 'job',
                    'unit_price' => 1200,
                ],
            ],
        ];

        $this->post(route('documents.store', 'customer-pos'), $payload)
            ->assertRedirect();

        $customerPo = Document::where('external_reference', 'DIRECT-CPO-001')->firstOrFail();

        $this->assertNull($customerPo->related_document_id);
        $this->assertSame('direct_customer_po', $customerPo->source_type);
        $this->assertSame('Direct customer PO', $customerPo->sourceTypeDisplay());
        $this->assertSame('Customer sent PO directly under an agreed rate card.', $customerPo->source_note);

        $customerPo->update(['status' => 'issued']);
        $this->assertSame('Accepted', $customerPo->fresh()->statusDisplay());
    }

    public function test_direct_exception_source_types_require_source_note(): void
    {
        foreach ($this->directExceptionCases() as $case) {
            $payload = $this->documentPayload($case['module'], $case['overrides']);
            unset($payload['source_note']);

            $this->post(route('documents.store', $case['module']), $payload)
                ->assertSessionHasErrors('source_note');

            $this->assertDatabaseMissing('documents', [
                'external_reference' => $case['overrides']['external_reference'],
            ]);
        }
    }

    public function test_direct_exception_source_types_save_with_source_note_and_audit_context(): void
    {
        foreach ($this->directExceptionCases() as $case) {
            $note = 'Approved exception: '.$case['overrides']['external_reference'];
            $payload = $this->documentPayload($case['module'], $case['overrides'] + [
                'source_note' => $note,
            ]);

            $this->post(route('documents.store', $case['module']), $payload)
                ->assertRedirect();

            $document = Document::where('external_reference', $case['overrides']['external_reference'])->firstOrFail();
            $this->assertSame($case['overrides']['source_type'], $document->source_type);
            $this->assertSame($note, $document->source_note);

            $audit = AuditTrail::where('action', 'document_created')
                ->where('auditable_type', Document::class)
                ->where('auditable_id', $document->id)
                ->firstOrFail();

            $this->assertSame($case['overrides']['source_type'], $audit->after_values['source_type'] ?? null);
            $this->assertSame($note, $audit->after_values['source_note'] ?? null);
        }
    }

    public function test_normal_source_path_still_works_without_exception_note(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'NORMAL-SOURCE-QUOTE',
            'description' => 'Quotation used as normal PO source',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->submitAndApprove($quotation);

        $payload = $this->documentPayload('customer-pos', [
            'customer_id' => $this->customer->id,
            'related_document_id' => $quotation->id,
            'source_type' => 'quotation',
            'external_reference' => 'NORMAL-SOURCE-CPO',
            'description' => 'Customer PO from approved quotation',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        unset($payload['source_note']);

        $this->post(route('documents.store', 'customer-pos'), $payload)
            ->assertRedirect();

        $customerPo = Document::where('external_reference', 'NORMAL-SOURCE-CPO')->firstOrFail();
        $this->assertSame($quotation->id, $customerPo->related_document_id);
        $this->assertSame('quotation', $customerPo->source_type);
        $this->assertNull($customerPo->source_note);
    }

    public function test_goods_receipt_normal_flow_requires_issued_supplier_po(): void
    {
        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'GR-SOURCE-SPO',
            'description' => 'Supplier PO for receiving validation',
            'quantity' => 3,
            'unit_price' => 450,
        ]);

        $this->post(route('documents.store', 'goods-receipts'), $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'GR-DRAFT-SPO-BLOCKED',
            'description' => 'Receiving should wait for issued PO',
            'quantity' => 3,
            'unit_price' => 450,
        ]))->assertSessionHasErrors('related_document_id');

        $this->submitAndApprove($supplierPo);

        $this->post(route('documents.store', 'goods-receipts'), $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'GR-APPROVED-SPO-BLOCKED',
            'description' => 'Receiving should wait for issued PO',
            'quantity' => 3,
            'unit_price' => 450,
        ]))->assertSessionHasErrors('related_document_id');

        $this->transition($supplierPo, 'issue', 'issued');

        $this->post(route('documents.store', 'goods-receipts'), $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'GR-ISSUED-SPO-OK',
            'description' => 'Receiving against issued supplier PO',
            'quantity' => 3,
            'unit_price' => 450,
        ]))->assertRedirect();

        $goodsReceipt = Document::where('external_reference', 'GR-ISSUED-SPO-OK')->firstOrFail();
        $this->assertSame($supplierPo->id, $goodsReceipt->related_document_id);
        $this->assertSame('supplier_po', $goodsReceipt->source_type);
        $this->assertNull($goodsReceipt->source_note);
    }

    public function test_goods_receipt_rejects_supplier_po_from_another_supplier(): void
    {
        $otherSupplier = Supplier::create([
            'name' => 'Other Supplier Sdn Bhd',
            'code' => 'OTHER',
            'category' => 'Materials / Hardware',
            'email' => 'accounts@other.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $otherSupplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $otherSupplier->id,
            'external_reference' => 'GR-OTHER-SPO',
            'description' => 'Issued PO for another supplier',
            'quantity' => 2,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($otherSupplierPo);
        $this->transition($otherSupplierPo, 'issue', 'issued');

        $this->post(route('documents.store', 'goods-receipts'), $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $otherSupplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'GR-OTHER-SUPPLIER-BLOCKED',
            'description' => 'Receiving should match selected supplier',
            'quantity' => 2,
            'unit_price' => 450,
        ]))->assertSessionHasErrors('related_document_id');
    }

    public function test_direct_receipt_exception_requires_and_saves_source_note(): void
    {
        $blockedPayload = $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_receipt',
            'external_reference' => 'DIRECT-GR-BLOCKED',
            'description' => 'Direct receipt missing explanation',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        unset($blockedPayload['source_note']);

        $this->post(route('documents.store', 'goods-receipts'), $blockedPayload)
            ->assertSessionHasErrors('source_note');

        $this->post(route('documents.store', 'goods-receipts'), $this->documentPayload('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_receipt',
            'source_note' => 'Delivery arrived before PO was available; manager approved direct receipt.',
            'external_reference' => 'DIRECT-GR-OK',
            'description' => 'Direct receipt with explanation',
            'quantity' => 1,
            'unit_price' => 450,
        ]))->assertRedirect();

        $goodsReceipt = Document::where('external_reference', 'DIRECT-GR-OK')->firstOrFail();
        $this->assertNull($goodsReceipt->related_document_id);
        $this->assertSame('direct_receipt', $goodsReceipt->source_type);
        $this->assertSame('Delivery arrived before PO was available; manager approved direct receipt.', $goodsReceipt->source_note);
    }

    public function test_pdf_attachment_document_csv_and_report_csv_outputs_work(): void
    {
        Storage::fake('local');

        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TEST-OUTPUT-INV',
            'description' => 'Invoice used for output checks',
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $this->submitAndApprove($invoice);
        $this->transition($invoice, 'issue', 'issued');

        $pdfResponse = $this->get(route('documents.pdf', $invoice));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('content-type'));
        $this->assertStringContainsString('inline', $pdfResponse->headers->get('content-disposition'));
        $this->assertStringContainsString($invoice->document_number.'.pdf', $pdfResponse->headers->get('content-disposition'));

        $downloadResponse = $this->get(route('documents.pdf.download', $invoice));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', $downloadResponse->headers->get('content-disposition'));

        $upload = UploadedFile::fake()->create('supporting-note.txt', 4, 'text/plain');
        $this->post(route('documents.attachments.store', $invoice), [
            'attachment' => $upload,
            'category' => 'delivery_evidence',
        ])->assertRedirect();

        $attachment = Attachment::where('document_id', $invoice->id)->firstOrFail();
        $this->assertSame('supporting-note.txt', $attachment->original_name);
        $this->assertSame('delivery_evidence', $attachment->category);
        Storage::disk('local')->assertExists($attachment->path);

        $documentCsv = $this->get(route('documents.export', 'customer-invoices'));
        $documentCsv->assertOk();
        $this->assertStringContainsString('Number,Status,Party', $documentCsv->streamedContent());
        $this->assertStringContainsString($invoice->document_number, $documentCsv->streamedContent());

        $reportCsv = $this->get(route('reports.export', ['report' => 'receivables']));
        $reportCsv->assertOk();
        $this->assertStringContainsString('Document,Party,"Issue Date","Due Date",Status,Total,Paid,Balance', $reportCsv->streamedContent());
        $this->assertStringContainsString($invoice->document_number, $reportCsv->streamedContent());
    }

    public function test_reports_page_shows_finance_report_workbench_with_open_balances(): void
    {
        $customerInvoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'REPORT-CUSTOMER-INV',
            'description' => 'Customer invoice for report page',
            'quantity' => 1,
            'unit_price' => 1000,
        ]);
        $customerInvoice->forceFill([
            'status' => 'issued',
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ])->save();

        Payment::create([
            'document_id' => $customerInvoice->id,
            'direction' => 'incoming',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 200,
            'method' => 'Bank transfer',
            'reference' => 'REPORT-INCOMING-PART',
            'created_by' => $this->admin->id,
        ]);

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'REPORT-SUPPLIER-INV',
            'description' => 'Supplier invoice for report page',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $supplierInvoice->forceFill([
            'status' => 'matched',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ])->save();

        $response = $this->get(route('reports.index', ['range' => 60]));

        $response->assertOk();
        $response->assertSee('Finance reports');
        $response->assertSee('Receivables and payables');
        $response->assertSee('Overdue receivables');
        $response->assertSee('Supplier payments due soon');
        $response->assertSee('Cash movement');
        $response->assertSee('Receivables aging');
        $response->assertSee('Payables aging');
        $response->assertSee('12-month invoice movement');
        $response->assertSee('Download CSV');
        $response->assertSee($customerInvoice->document_number);
        $response->assertSee($supplierInvoice->document_number);
        $response->assertSee('MYR 880.00');
        $response->assertSee('Showing 8 earliest open receivables');
        $response->assertSee('Receivables CSV');
        $response->assertSee('Payables CSV');
        $response->assertSee('Payments CSV');
        $response->assertDontSee('date-control', false);
        $response->assertDontSee('Incoming payments, last 30 days');
    }

    public function test_supplier_invoice_preview_uses_uploaded_supplier_file_not_company_letterhead(): void
    {
        Storage::fake('local');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'SUPPLIER-INV-8891',
            'description' => 'Supplier invoice file matching line',
            'quantity' => 1,
            'unit_price' => 888,
        ]);

        $upload = UploadedFile::fake()->create('supplier-tax-invoice-8891.pdf', 120, 'application/pdf');
        $this->post(route('documents.attachments.store', $supplierInvoice), [
            'attachment' => $upload,
            'category' => 'invoice_copy',
        ])->assertRedirect();

        $attachment = Attachment::where('document_id', $supplierInvoice->id)->firstOrFail();
        $this->assertTrue($attachment->isPreviewable());

        $index = $this->get(route('documents.index', 'supplier-invoices'));
        $index->assertOk();
        $index->assertSee('Supplier invoices');
        $index->assertSee('Supplier invoice file');
        $index->assertDontSee('document-preview-toolbar', false);
        $index->assertDontSee('Incoming supplier document');
        $index->assertSee('supplier-tax-invoice-8891.pdf');
        $index->assertSee(route('attachments.preview', $attachment), false);
        $index->assertSee('<iframe', false);
        $index->assertDontSee('Extracted invoice details');
        $index->assertDontSee('System matching summary');
        $index->assertDontSee('Purchase request to supplier quotation');
        $index->assertDontSee('RC TECHNOLOGY RESOURCES');
        $index->assertDontSee('Reliable Infrastructure. Connected Future.');

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Supplier invoice review');
        $show->assertSee('supplier-tax-invoice-8891.pdf');
        $show->assertDontSee('Preview PDF');
        $show->assertDontSee('Download PDF');

        $preview = $this->get(route('attachments.preview', $attachment));
        $preview->assertOk();
        $this->assertStringContainsString('application/pdf', $preview->headers->get('content-type'));
        $this->assertStringContainsString('inline', $preview->headers->get('content-disposition'));

        $imageInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'SUPPLIER-INV-IMAGE',
            'description' => 'Supplier invoice image matching line',
            'quantity' => 1,
            'unit_price' => 456,
        ]);

        $imageUpload = UploadedFile::fake()->image('supplier-invoice-photo.jpg', 900, 1200);
        $this->post(route('documents.attachments.store', $imageInvoice), [
            'attachment' => $imageUpload,
            'category' => 'invoice_copy',
        ])->assertRedirect();

        $imageAttachment = Attachment::where('document_id', $imageInvoice->id)->firstOrFail();
        $imageShow = $this->get(route('documents.show', $imageInvoice));
        $imageShow->assertOk();
        $imageShow->assertSee('<img src="'.route('attachments.preview', $imageAttachment).'"', false);

        $imagePreview = $this->get(route('attachments.preview', $imageAttachment));
        $imagePreview->assertOk();
        $this->assertStringContainsString('image/', $imagePreview->headers->get('content-type'));
        $this->assertStringContainsString('inline', $imagePreview->headers->get('content-disposition'));
    }

    public function test_received_procurement_previews_do_not_use_company_letterhead(): void
    {
        Storage::fake('local');

        $customerPo = $this->createDocument('customer-pos', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CUSTOMER-PO-7715',
            'description' => 'Customer supplied purchase order scope',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        $this->post(route('documents.attachments.store', $customerPo), [
            'attachment' => UploadedFile::fake()->create('customer-po-7715.pdf', 60, 'application/pdf'),
            'category' => 'customer_po',
        ])->assertRedirect();

        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'PR-SITE-MATERIALS',
            'description' => 'Internal purchase request for site materials',
            'quantity' => 3,
            'unit_price' => 250,
        ]);

        $supplierQuotation = $this->createDocument('supplier-quotations', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'SUPPLIER-QUOTE-7715',
            'description' => 'Supplier quotation materials package',
            'quantity' => 3,
            'unit_price' => 225,
        ]);
        $this->post(route('documents.attachments.store', $supplierQuotation), [
            'attachment' => UploadedFile::fake()->create('supplier-quote-7715.pdf', 80, 'application/pdf'),
            'category' => 'supplier_quote',
        ])->assertRedirect();

        $goodsReceipt = $this->createDocument('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'GR-SERVICE-7715',
            'description' => 'Received site support service',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->post(route('documents.attachments.store', $goodsReceipt), [
            'attachment' => UploadedFile::fake()->image('service-report-7715.jpg', 900, 1200),
            'category' => 'service_report',
        ])->assertRedirect();

        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'OWN-PO-7715',
            'description' => 'Company issued supplier purchase order',
            'quantity' => 2,
            'unit_price' => 500,
        ]);

        $customerPoIndex = $this->get(route('documents.index', 'customer-pos'));
        $customerPoIndex->assertOk();
        $customerPoIndex->assertSee('Customer PO file');
        $customerPoIndex->assertSee('Customer PO received');
        $customerPoIndex->assertSee('customer-po-7715.pdf');
        $customerPoIndex->assertSee('<iframe', false);
        $customerPoIndex->assertDontSee('Reliable Infrastructure. Connected Future.');

        $purchaseRequestIndex = $this->get(route('documents.index', 'purchase-requests'));
        $purchaseRequestIndex->assertOk();
        $purchaseRequestIndex->assertSee('No supporting file uploaded yet');
        $purchaseRequestIndex->assertSee('Requested supplier');
        $purchaseRequestIndex->assertSee('Internal purchase request for site materials');
        $purchaseRequestIndex->assertDontSee('Reliable Infrastructure. Connected Future.');

        $supplierQuoteIndex = $this->get(route('documents.index', 'supplier-quotations'));
        $supplierQuoteIndex->assertOk();
        $supplierQuoteIndex->assertSee('Supplier quotation file');
        $supplierQuoteIndex->assertSee('Supplier quotation received');
        $supplierQuoteIndex->assertSee('supplier-quote-7715.pdf');
        $supplierQuoteIndex->assertSee('<iframe', false);
        $supplierQuoteIndex->assertDontSee('Reliable Infrastructure. Connected Future.');

        $goodsReceiptIndex = $this->get(route('documents.index', 'goods-receipts'));
        $goodsReceiptIndex->assertOk();
        $goodsReceiptIndex->assertSee('Goods receipt evidence');
        $goodsReceiptIndex->assertSee('service-report-7715.jpg');
        $goodsReceiptIndex->assertSee('<img src="'.route('attachments.preview', $goodsReceipt->attachments()->first()).'"', false);
        $goodsReceiptIndex->assertDontSee('Reliable Infrastructure. Connected Future.');

        $supplierQuoteShow = $this->get(route('documents.show', $supplierQuotation));
        $supplierQuoteShow->assertOk();
        $supplierQuoteShow->assertSee('Supplier quotation received');
        $supplierQuoteShow->assertDontSee('Preview PDF');
        $supplierQuoteShow->assertDontSee('Download PDF');
        $supplierQuoteShow->assertDontSee('Reliable Infrastructure. Connected Future.');

        $supplierPoIndex = $this->get(route('documents.index', 'supplier-pos'));
        $supplierPoIndex->assertOk();
        $supplierPoIndex->assertSee($supplierPo->document_number);
        $supplierPoIndex->assertSee('Purchase order PDF');
        $supplierPoIndex->assertDontSee('Reliable Infrastructure. Connected Future.');
    }

    public function test_issued_supplier_po_listing_uses_final_pdf_output_preview_while_draft_stays_draft_preview(): void
    {
        $draftPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'DRAFT-SPO-PREVIEW',
            'description' => 'Draft supplier purchase order line',
            'quantity' => 1,
            'unit_price' => 300,
        ]);

        $issuedPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'ISSUED-SPO-PREVIEW',
            'description' => 'Issued supplier purchase order line',
            'quantity' => 2,
            'unit_price' => 450,
        ]);

        $this->submitAndApprove($issuedPo);
        $this->post(route('documents.transition', [$issuedPo, 'issue']))
            ->assertRedirect();

        $index = $this->get(route('documents.index', 'supplier-pos'));

        $index->assertOk();
        $index->assertSee($issuedPo->document_number);
        $index->assertSee('Issued purchase order PDF');
        $index->assertSee('This is the issued purchase order PDF sent to the supplier.');
        $index->assertSee(route('documents.pdf', $issuedPo), false);
        $index->assertSee('data-generated-pdf-preview', false);
        $index->assertSee($draftPo->document_number);
        $index->assertSee('data-quotation-preview-card', false);
        $index->assertDontSee(route('documents.pdf', $draftPo), false);

        $issuedShow = $this->get(route('documents.show', $issuedPo));
        $issuedShow->assertOk();
        $issuedShow->assertSee('Issued purchase order PDF');
        $issuedShow->assertSee(route('documents.pdf', $issuedPo), false);

        $draftShow = $this->get(route('documents.show', $draftPo));
        $draftShow->assertOk();
        $draftShow->assertDontSee('Final document output');
        $draftShow->assertDontSee(route('documents.pdf', $draftPo), false);
    }

    public function test_final_system_generated_procurement_records_use_pdf_output_preview(): void
    {
        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'APPROVED-PR-PREVIEW',
            'description' => 'Approved purchase request line',
            'quantity' => 1,
            'unit_price' => 800,
        ]);
        $this->submitAndApprove($purchaseRequest);

        $goodsReceipt = $this->createDocument('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'RECEIVED-GR-PREVIEW',
            'description' => 'Received service record line',
            'quantity' => 1,
            'unit_price' => 500,
        ]);
        $this->submitAndApprove($goodsReceipt);
        $this->post(route('documents.transition', [$goodsReceipt, 'receive']))
            ->assertRedirect();

        $purchaseRequestIndex = $this->get(route('documents.index', 'purchase-requests'));
        $purchaseRequestIndex->assertOk();
        $purchaseRequestIndex->assertSee('Approved purchase request PDF');
        $purchaseRequestIndex->assertSee(route('documents.pdf', $purchaseRequest), false);

        $goodsReceiptIndex = $this->get(route('documents.index', 'goods-receipts'));
        $goodsReceiptIndex->assertOk();
        $goodsReceiptIndex->assertSee('Service acceptance record PDF');
        $goodsReceiptIndex->assertSee(route('documents.pdf', $goodsReceipt), false);
        $goodsReceiptIndex->assertSee('Received date');
        $goodsReceiptIndex->assertSee('1 received');
    }

    public function test_goods_receipt_pdf_uses_receiving_format_without_commercial_totals(): void
    {
        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'PO-FOR-GR-FORMAT',
            'description' => 'Ordered installation service',
            'quantity' => 5,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $goodsReceipt = $this->createDocument('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'external_reference' => 'DO-FORMAT-7715',
            'description' => 'Received installation service',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $goodsReceipt->update(['status' => 'received']);
        Attachment::create([
            'document_id' => $goodsReceipt->id,
            'category' => 'uat_document',
            'original_name' => 'signed-uat-acceptance.pdf',
            'path' => 'attachments/test/signed-uat-acceptance.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'uploaded_by' => $this->admin->id,
        ]);

        $html = view('documents.pdf', [
            'document' => $goodsReceipt->load(['customer', 'supplier', 'relatedDocument.items', 'items.product', 'billingStages', 'payments', 'attachments', 'creator', 'approver']),
            'meta' => Document::metaForSlug(Document::slugForType($goodsReceipt->type)),
        ])->render();

        $this->assertStringContainsString('SERVICE ACCEPTANCE RECORD', $html);
        $this->assertStringContainsString('Service Acceptance Details', $html);
        $this->assertStringContainsString('Accepted Service / Deliverable', $html);
        $this->assertStringContainsString('PO Qty', $html);
        $this->assertStringContainsString('Accepted Qty', $html);
        $this->assertStringContainsString('Exception Qty', $html);
        $this->assertStringContainsString('Supporting Evidence', $html);
        $this->assertStringContainsString('UAT / acceptance sign-off', $html);
        $this->assertStringContainsString('signed-uat-acceptance.pdf', $html);
        $this->assertStringContainsString('Service Acceptance Declaration', $html);
        $this->assertStringContainsString('Approved For Matching', $html);
        $this->assertStringNotContainsString('Payment Terms', $html);
        $this->assertStringNotContainsString('Unit Price', $html);
        $this->assertStringNotContainsString('Subtotal', $html);
        $this->assertStringNotContainsString('Tax', $html);
        $this->assertStringNotContainsString('MYR 2,430.00', $html);
    }

    public function test_goods_receipt_create_form_uses_receiving_input_not_commercial_input(): void
    {
        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'PO-FOR-RECEIPT-INPUT',
            'description' => 'Ordered installation service',
            'quantity' => 5,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $response = $this->get(route('documents.create', [
            'module' => 'goods-receipts',
            'source_document_id' => $supplierPo->id,
        ]));

        $response->assertOk();
        $response->assertSee('Record goods receipt');
        $response->assertSee('Supplier and receipt details');
        $response->assertSee('Evidence after save');
        $response->assertSee('Received items');
        $response->assertSee('Material / goods receipt');
        $response->assertSee('Received qty');
        $response->assertSee('Short / rejected');
        $response->assertSee('Goods receipt preview');
        $response->assertSee('GOODS RECEIPT NOTE');
        $response->assertSee('Evidence to attach after save');
        $response->assertDontSee('Receipt / Acceptance Preview');
        $response->assertDontSee('RECEIPT / ACCEPTANCE');
        $response->assertDontSee('Payment term days');
        $response->assertDontSee('Tax rate for totals');
        $response->assertDontSee('<th>Price</th>', false);

        $this->post(route('documents.store', 'goods-receipts'), [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'DO-MISSING-PO',
            'issue_date' => '2026-05-14',
            'currency' => 'MYR',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Received without linked PO',
                    'quantity' => 1,
                    'unit' => 'job',
                    'unit_price' => 0,
                ],
            ],
        ])->assertSessionHasErrors('related_document_id');
    }

    public function test_supplier_invoice_ocr_draft_must_be_verified_before_approval(): void
    {
        Storage::fake('local');
        $this->fakeTesseractExtractor([
            'supplier_name' => 'Best Supplies Sdn Bhd',
            'invoice_number' => 'N2N-INV-7715',
            'invoice_date' => '2026-05-14',
            'po_number' => 'SPO-2026-00002',
            'subtotal' => '2250.00',
            'tax_total' => '180.00',
            'total' => '2430.00',
            'payment_terms' => '14 days from invoice date',
        ]);

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'DRAFT-SUPPLIER-INV',
            'description' => 'Supplier invoice awaiting OCR verification',
            'quantity' => 5,
            'unit_price' => 450,
        ]);

        $upload = UploadedFile::fake()->image('n2n-system-invoice-7715.jpg', 900, 1200);
        $this->post(route('documents.attachments.store', $supplierInvoice), [
            'attachment' => $upload,
            'category' => 'invoice_copy',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Attachment uploaded. Extracted details are ready for review.');

        $attachment = Attachment::where('document_id', $supplierInvoice->id)->firstOrFail();
        $extraction = AttachmentExtraction::where('attachment_id', $attachment->id)->firstOrFail();

        $this->assertSame('processed', $extraction->status);
        $this->assertSame('N2N-INV-7715', $extraction->extracted_fields['invoice_number']);

        $this->post(route('documents.submit', $supplierInvoice))
            ->assertRedirect()
            ->assertSessionHasErrors('approval');
        $this->assertSame('draft', $supplierInvoice->refresh()->status);

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Supplier invoice details');
        $show->assertSee('Ready to verify');
        $show->assertSee('Verify invoice details');

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => [
                'supplier_name' => 'Best Supplies Sdn Bhd',
                'invoice_number' => 'N2N-INV-7715',
                'invoice_date' => '2026-05-14',
                'po_number' => 'SPO-2026-00002',
                'subtotal' => '2250.00',
                'tax_total' => '180.00',
                'total' => '2430.00',
                'payment_terms' => '14 days from invoice date',
            ],
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'Extracted details checked against the uploaded supplier invoice.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Supplier invoice extraction verified.');

        $supplierInvoice->refresh();
        $this->assertSame('N2N-INV-7715', $supplierInvoice->external_reference);
        $this->assertSame('2026-05-14', $supplierInvoice->issue_date->toDateString());
        $this->assertSame('2430.00', $supplierInvoice->total);
        $this->assertSame('verified', $extraction->refresh()->status);

        $this->post(route('documents.submit', $supplierInvoice))
            ->assertRedirect();
        $this->assertSame('pending_approval', $supplierInvoice->refresh()->status);
    }

    public function test_supplier_quotation_ocr_draft_can_be_verified_and_updates_quote_record(): void
    {
        Storage::fake('local');
        $this->fakeTesseractExtractor([
            'supplier_name' => 'Best Supplies Sdn Bhd',
            'quote_number' => 'SQ-BEST-009',
            'quote_date' => '2026-05-14',
            'valid_until' => '2026-06-13',
            'subtotal' => '2430.00',
            'tax_total' => '0.00',
            'total' => '2430.00',
            'payment_terms' => '30 days from invoice date',
            'items' => [
                [
                    'description' => 'Operations hardware kit',
                    'quantity' => '5',
                    'unit' => 'set',
                    'unit_price' => '450.00',
                    'tax_rate' => '0',
                ],
                [
                    'description' => 'Service coordination',
                    'quantity' => '1',
                    'unit' => 'job',
                    'unit_price' => '180.00',
                    'tax_rate' => '0',
                ],
            ],
        ], 'Supplier quotation OCR text');

        $supplierQuotation = $this->createDocument('supplier-quotations', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'DRAFT-SQ-001',
            'description' => 'Placeholder supplier quotation line',
            'quantity' => 1,
            'unit_price' => 1,
        ]);

        $this->post(route('documents.attachments.store', $supplierQuotation), [
            'attachment' => UploadedFile::fake()->image('n2n-system-quote-009.jpg', 900, 1200),
            'category' => 'supplier_quote',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Attachment uploaded. Supplier quotation details are ready for review.');

        $attachment = Attachment::where('document_id', $supplierQuotation->id)->firstOrFail();
        $extraction = AttachmentExtraction::where('attachment_id', $attachment->id)->firstOrFail();

        $this->assertSame('processed', $extraction->status);
        $this->assertSame('SQ-BEST-009', $extraction->extracted_fields['quote_number']);

        $show = $this->get(route('documents.show', $supplierQuotation));
        $show->assertOk();
        $show->assertSee('Supplier quotation review');
        $show->assertSee('Details ready for review');
        $show->assertSee('Verify supplier quotation');

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => [
                'supplier_name' => 'Best Supplies Sdn Bhd',
                'quote_number' => 'SQ-BEST-009',
                'quote_date' => '2026-05-14',
                'valid_until' => '2026-06-13',
                'subtotal' => '2430.00',
                'tax_total' => '0.00',
                'total' => '2430.00',
                'payment_terms' => '30 days from invoice date',
                'commercial_terms' => 'Delivery: Ex-stock subject to availability.',
            ],
            'items' => [
                [
                    'description' => 'Operations hardware kit',
                    'quantity' => '5',
                    'unit' => 'set',
                    'unit_price' => '450.00',
                    'tax_rate' => '0',
                ],
                [
                    'description' => 'Service coordination',
                    'quantity' => '1',
                    'unit' => 'job',
                    'unit_price' => '180.00',
                    'tax_rate' => '0',
                ],
            ],
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'OCR-assisted supplier quotation details checked against the uploaded source.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Supplier quotation details verified.');

        $supplierQuotation->refresh();
        $supplierQuotation->load('items');

        $this->assertSame('SQ-BEST-009', $supplierQuotation->external_reference);
        $this->assertSame('2026-05-14', $supplierQuotation->issue_date->toDateString());
        $this->assertSame('2026-06-13', $supplierQuotation->due_date->toDateString());
        $this->assertSame('Delivery: Ex-stock subject to availability.', $supplierQuotation->terms);
        $this->assertSame('2430.00', $supplierQuotation->total);
        $this->assertCount(2, $supplierQuotation->items);
        $this->assertSame('Operations hardware kit', $supplierQuotation->items[0]->description);
        $this->assertSame('450.00', $supplierQuotation->items[0]->unit_price);
        $this->assertSame('verified', $extraction->refresh()->status);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'supplier_quotation_details_verified',
            'auditable_type' => AttachmentExtraction::class,
            'auditable_id' => $extraction->id,
        ]);

        $index = $this->get(route('documents.index', 'supplier-quotations'));
        $index->assertOk();
        $index->assertDontSee('Run OCR');
        $index->assertDontSee('Verify and update supplier quotation');
    }

    public function test_supplier_quotation_with_ocr_failure_can_still_be_verified_from_source_file(): void
    {
        Storage::fake('local');
        $this->fakeFailingTesseractExtractor('Tesseract could not read this supplier quotation image.');

        $supplierQuotation = $this->createDocument('supplier-quotations', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'MANUAL-SQ-001',
            'description' => 'Supplier quotation requiring manual verification',
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->post(route('documents.attachments.store', $supplierQuotation), [
            'attachment' => UploadedFile::fake()->image('unreadable-supplier-quote.jpg', 900, 1200),
            'category' => 'supplier_quote',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Attachment uploaded. OCR could not read the supplier quotation; verify the quotation details manually from the uploaded file.');

        $extraction = AttachmentExtraction::where('document_id', $supplierQuotation->id)->firstOrFail();
        $this->assertSame('failed', $extraction->status);

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => [
                'supplier_name' => 'Best Supplies Sdn Bhd',
                'quote_number' => 'MANUAL-SQ-001',
                'quote_date' => '2026-05-15',
                'total' => '100.00',
            ],
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'Verified manually because OCR could not read the supplier quotation file.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Supplier quotation details verified.');

        $this->assertSame('verified', $extraction->refresh()->status);
        $this->assertSame('manual', $extraction->verification_method);
        $this->assertSame('2026-05-15', $supplierQuotation->refresh()->issue_date->toDateString());
    }

    public function test_purchase_request_submission_requires_supplier_quote_or_quote_exception(): void
    {
        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'PR-QUOTE-GATE',
            'description' => 'Purchase request without supplier quotation evidence',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $purchaseRequest->forceFill([
            'source_type' => 'supplier_quote',
            'source_note' => null,
        ])->save();

        $this->from(route('documents.show', $purchaseRequest))
            ->post(route('documents.submit', $purchaseRequest))
            ->assertRedirect(route('documents.show', $purchaseRequest))
            ->assertSessionHasErrors('approval');

        $this->assertSame('draft', $purchaseRequest->refresh()->status);

        $payload = $this->documentPayload('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'quote_exception',
            'external_reference' => 'PR-EXCEPTION-NO-REASON',
            'description' => 'Purchase request exception without reason',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->post(route('documents.store', 'purchase-requests'), $payload)
            ->assertSessionHasErrors('source_note');
    }

    public function test_purchase_request_supplier_quote_ocr_must_be_verified_before_approval(): void
    {
        Storage::fake('local');
        $quoteItems = [
            [
                'description' => 'Operations hardware kit',
                'quantity' => '2',
                'unit' => 'set',
                'unit_price' => '450.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Sensor modules',
                'quantity' => '3',
                'unit' => 'unit',
                'unit_price' => '250.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Installation preparation',
                'quantity' => '1',
                'unit' => 'job',
                'unit_price' => '300.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Cabling kit',
                'quantity' => '4',
                'unit' => 'set',
                'unit_price' => '75.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Service coordination',
                'quantity' => '1',
                'unit' => 'job',
                'unit_price' => '120.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Project documentation',
                'quantity' => '1',
                'unit' => 'set',
                'unit_price' => '40.00',
                'tax_rate' => '0',
            ],
            [
                'description' => 'Delivery charges',
                'quantity' => '1',
                'unit' => 'trip',
                'unit_price' => '20.00',
                'tax_rate' => '0',
            ],
        ];

        $this->fakeTesseractExtractor([
            'supplier_name' => 'Best Supplies Sdn Bhd',
            'quote_number' => 'SQ-BEST-PR-009',
            'quote_date' => '2026-05-14',
            'valid_until' => '2026-06-13',
            'subtotal' => '2430.00',
            'tax_total' => '0.00',
            'total' => '2430.00',
            'payment_terms' => '30 days from invoice date',
            'commercial_terms' => 'Delivery: Ex-stock subject to availability.',
            'items' => $quoteItems,
        ], 'Supplier quotation OCR text for purchase request approval');

        $createForm = $this->get(route('documents.create', 'purchase-requests'));
        $createForm->assertOk();
        $createForm->assertSee('Supplier quotation upload');
        $createForm->assertSeeText('Create draft');
        $createForm->assertSeeText('run OCR');
        $createForm->assertSee('data-pr-create-form', false);
        $createForm->assertSee('data-pr-primary-submit', false);
        $createForm->assertSee('name="source_attachment"', false);

        $payload = $this->documentPayload('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'supplier_quote',
            'external_reference' => 'PR-QUOTE-OCR',
            'description' => 'Purchase request backed by supplier quotation',
            'quantity' => 1,
            'unit_price' => 1,
        ]);
        unset($payload['items']);
        $payload['source_attachment'] = UploadedFile::fake()->image('supplier-quote-for-pr.jpg', 900, 1200);

        $this->post(route('documents.store', 'purchase-requests'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Purchase request created. Supplier quotation details are ready for review.');

        $purchaseRequest = Document::where('external_reference', 'PR-QUOTE-OCR')->firstOrFail();
        $purchaseRequest->load('items');
        $this->assertCount(0, $purchaseRequest->items);
        $this->assertSame('0.00', $purchaseRequest->total);

        $attachment = Attachment::where('document_id', $purchaseRequest->id)
            ->where('category', 'supplier_quote')
            ->firstOrFail();
        $extraction = AttachmentExtraction::where('attachment_id', $attachment->id)->firstOrFail();

        $this->assertSame('processed', $extraction->status);
        $this->assertSame('SQ-BEST-PR-009', $extraction->extracted_fields['quote_number']);

        $this->from(route('documents.show', $purchaseRequest))
            ->post(route('documents.submit', $purchaseRequest))
            ->assertRedirect(route('documents.show', $purchaseRequest))
            ->assertSessionHasErrors('approval');
        $this->assertSame('draft', $purchaseRequest->refresh()->status);

        $show = $this->get(route('documents.show', $purchaseRequest));
        $show->assertOk();
        $show->assertSee('Next action');
        $show->assertSee('Details ready for review');
        $show->assertSee('Verify supplier quotation evidence');
        $show->assertDontSee('Required before continuing');
        $show->assertDontSee('Supplier quotation details ready for review');
        $show->assertSee('Detected quotation MYR 2,430.00');
        $show->assertSee('Payment terms');
        $show->assertSee('30 days from invoice date');
        $show->assertSee('Terms and conditions');
        $show->assertSee('Delivery: Ex-stock subject to availability.');
        $show->assertSee('7 selected');
        $show->assertSee('Only selected quotation lines become purchase request items.');
        $show->assertSee('Operations hardware kit');
        $show->assertSee('Delivery charges');
        $this->assertSame(1, substr_count($show->getContent(), 'Terms and conditions'));
        $show->assertDontSee('Document details');
        $show->assertDontSee('Document progress');
        $show->assertDontSee('Evidence and attachments');
        $show->assertDontSee('Commercial notes');
        $show->assertDontSee('No payments recorded.');
        $show->assertDontSee('No approval events.');
        $show->assertDontSee('Supplier quotation OCR pending verification');

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => [
                'supplier_name' => 'Best Supplies Sdn Bhd',
                'quote_number' => 'SQ-BEST-PR-009',
                'quote_date' => '2026-05-14',
                'valid_until' => '2026-06-13',
                'subtotal' => '2430.00',
                'tax_total' => '0.00',
                'total' => '2430.00',
                'payment_terms' => '30 days from invoice date',
                'commercial_terms' => 'Delivery: Ex-stock subject to availability.',
            ],
            'items' => $quoteItems,
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'Supplier quotation checked before purchase request approval.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Supplier quotation details confirmed.');

        $this->assertSame('SQ-BEST-PR-009', $purchaseRequest->refresh()->external_reference);
        $this->assertSame('Delivery: Ex-stock subject to availability.', $purchaseRequest->terms);
        $this->assertSame('verified', $extraction->refresh()->status);
        $purchaseRequest->load('items');
        $this->assertCount(7, $purchaseRequest->items);
        $this->assertSame('Operations hardware kit', $purchaseRequest->items[0]->description);
        $this->assertSame('Delivery charges', $purchaseRequest->items[6]->description);
        $this->assertSame(2430.00, (float) $purchaseRequest->total);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'purchase_request_quote_verified',
            'auditable_type' => AttachmentExtraction::class,
            'auditable_id' => $extraction->id,
        ]);

        $verifiedShow = $this->get(route('documents.show', $purchaseRequest));
        $verifiedShow->assertOk();
        $verifiedShow->assertSee('Submit this purchase request for approval.');
        $verifiedShow->assertSee('Submit for approval');
        $verifiedShow->assertSee('Requested items');
        $verifiedShow->assertSee('Operations hardware kit');
        $verifiedShow->assertSee('Delivery charges');
        $verifiedShow->assertSee('Supplier terms');
        $verifiedShow->assertSee('Delivery: Ex-stock subject to availability.');
        $verifiedShow->assertSee('Document details');
        $verifiedShow->assertSee('Supplier quotation file');
        $verifiedShow->assertDontSee('Required before continuing');
        $verifiedShow->assertDontSee('Available actions');
        $verifiedShow->assertDontSee('Document progress');
        $verifiedShow->assertDontSee('Evidence and attachments');
        $verifiedShow->assertDontSee('Attachment category');
        $verifiedShow->assertDontSee('Commercial notes');
        $verifiedShow->assertDontSee('No payments recorded.');
        $verifiedShow->assertDontSee('No approval events.');

        $this->post(route('documents.submit', $purchaseRequest))
            ->assertRedirect();
        $this->assertSame('pending_approval', $purchaseRequest->refresh()->status);

        $this->post(route('documents.approve', $purchaseRequest), [
            'comment' => 'Approved after checking verified supplier quotation evidence.',
        ])->assertRedirect();
        $this->assertSame('approved', $purchaseRequest->refresh()->status);

        $supplierQuotationForm = $this->get(route('documents.create', [
            'module' => 'supplier-quotations',
            'source_document_id' => $purchaseRequest->id,
        ]));
        $supplierQuotationForm->assertOk();
        $supplierQuotationForm->assertSee('SQ-BEST-PR-009');
        $supplierQuotationForm->assertSee('Operations hardware kit');
        $supplierQuotationForm->assertSee('Service coordination');
    }

    public function test_purchase_request_supplier_quote_verification_uses_only_selected_quote_lines(): void
    {
        $purchaseRequest = Document::create([
            'type' => 'purchase_request',
            'direction' => 'incoming',
            'document_number' => 'PR-SELECTED-LINES',
            'external_reference' => 'PR-SELECTED-LINES',
            'supplier_id' => $this->supplier->id,
            'source_type' => 'supplier_quote',
            'status' => 'draft',
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by' => $this->admin->id,
        ]);

        $attachment = Attachment::create([
            'document_id' => $purchaseRequest->id,
            'category' => 'supplier_quote',
            'original_name' => 'supplier-selected-lines.pdf',
            'path' => 'attachments/test/supplier-selected-lines.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'uploaded_by' => $this->admin->id,
        ]);

        $extraction = AttachmentExtraction::create([
            'attachment_id' => $attachment->id,
            'document_id' => $purchaseRequest->id,
            'status' => 'processed',
            'engine' => 'feature-test',
            'language' => 'eng',
            'raw_text' => 'Supplier quotation with optional delivery and selected hardware lines.',
            'extracted_fields' => [
                'supplier_name' => 'Best Supplies Sdn Bhd',
                'quote_number' => 'SQ-SELECTED-LINES',
                'quote_date' => '2026-05-14',
                'valid_until' => '2026-06-13',
                'total' => '800.00',
                'items' => [
                    ['description' => 'Control panel', 'quantity' => '2', 'unit' => 'unit', 'unit_price' => '100.00', 'tax_rate' => '0'],
                    ['description' => 'Optional delivery', 'quantity' => '5.000', 'unit' => 'trip', 'unit_price' => '50.00', 'tax_rate' => '0'],
                    ['description' => 'Installation kit', 'quantity' => '1.500', 'unit' => 'set', 'unit_price' => '350.00', 'tax_rate' => '0'],
                ],
            ],
        ]);

        $show = $this->get(route('documents.show', $purchaseRequest));
        $show->assertOk();
        $show->assertSee('Only selected quotation lines become purchase request items.');
        $show->assertSee('3 selected');
        $show->assertSee('Select');
        $show->assertSee('value="5"', false);
        $show->assertSee('value="1.5"', false);
        $show->assertDontSee('value="5.000"', false);
        $show->assertDontSee('value="1.500"', false);

        $fields = [
            'supplier_name' => 'Best Supplies Sdn Bhd',
            'quote_number' => 'SQ-SELECTED-LINES',
            'quote_date' => '2026-05-14',
            'valid_until' => '2026-06-13',
            'total' => '800.00',
            'payment_terms' => '30 days from invoice date',
            'commercial_terms' => 'Delivery optional.',
        ];

        $items = [
            ['included' => '1', 'description' => 'Control panel', 'quantity' => '2', 'unit' => 'unit', 'unit_price' => '100.00', 'tax_rate' => '0'],
            ['included' => '0', 'description' => 'Optional delivery', 'quantity' => '5', 'unit' => 'trip', 'unit_price' => '50.00', 'tax_rate' => '0'],
            ['included' => '1', 'description' => 'Installation kit', 'quantity' => '1', 'unit' => 'set', 'unit_price' => '350.00', 'tax_rate' => '0'],
        ];

        $this->from(route('documents.show', $purchaseRequest))
            ->put(route('attachment-extractions.verify', $extraction), [
                'fields' => $fields,
                'items' => collect($items)->map(fn (array $item) => array_merge($item, ['included' => '0']))->all(),
                'supplier_confirmed' => '1',
                'recorded_total_confirmed' => '1',
                'verification_notes' => 'No quote lines selected.',
            ])
            ->assertRedirect(route('documents.show', $purchaseRequest))
            ->assertSessionHasErrors('items');

        $this->assertSame('processed', $extraction->refresh()->status);

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => $fields,
            'items' => $items,
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'Only selected supplier quotation lines are needed for this purchase.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Supplier quotation details confirmed.');

        $purchaseRequest->refresh();
        $purchaseRequest->load('items');

        $this->assertSame('SQ-SELECTED-LINES', $purchaseRequest->external_reference);
        $this->assertSame('550.00', $purchaseRequest->total);
        $this->assertCount(2, $purchaseRequest->items);
        $this->assertSame('Control panel', $purchaseRequest->items[0]->description);
        $this->assertSame('Installation kit', $purchaseRequest->items[1]->description);
        $this->assertFalse($purchaseRequest->items->contains('description', 'Optional delivery'));

        $verifiedItems = $extraction->refresh()->verified_fields['items'];
        $this->assertCount(2, $verifiedItems);
        $this->assertSame('Control panel', $verifiedItems[0]['description']);
        $this->assertSame('Installation kit', $verifiedItems[1]['description']);
    }

    public function test_purchase_request_quote_exception_requires_approver_comment(): void
    {
        $purchaseRequest = $this->createDocument('purchase-requests', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'quote_exception',
            'source_note' => 'Emergency replacement approved before supplier quotation was available.',
            'external_reference' => 'PR-QUOTE-EXCEPTION',
            'description' => 'Emergency purchase request',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->submitForApproval($purchaseRequest);

        $show = $this->get(route('documents.show', $purchaseRequest));
        $show->assertOk();
        $show->assertSee('Quotation exception recorded');
        $show->assertSee('Approval comment');
        $show->assertSee('Confirm the quotation exception');

        $this->from(route('documents.show', $purchaseRequest))
            ->post(route('documents.approve', $purchaseRequest))
            ->assertRedirect(route('documents.show', $purchaseRequest))
            ->assertSessionHasErrors('comment');
        $this->assertSame('pending_approval', $purchaseRequest->refresh()->status);

        $this->post(route('documents.approve', $purchaseRequest), [
            'comment' => 'Approved exception because urgent delivery is required before formal quote arrives.',
        ])->assertRedirect();

        $this->assertSame('approved', $purchaseRequest->refresh()->status);
        $this->assertDatabaseHas('approvals', [
            'document_id' => $purchaseRequest->id,
            'status' => 'approved',
            'comment' => 'Approved exception because urgent delivery is required before formal quote arrives.',
        ]);
    }

    public function test_supplier_invoice_submission_requires_invoice_copy_and_verified_details(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Direct supplier invoice recorded for verification gate testing.',
            'external_reference' => 'VERIFY-GATE-001',
            'description' => 'Supplier invoice missing verification evidence',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.submit', $supplierInvoice))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('approval');
        $this->assertSame('draft', $supplierInvoice->refresh()->status);

        $this->uploadSupplierInvoiceCopy($supplierInvoice);

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.submit', $supplierInvoice))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('approval');
        $this->assertSame('draft', $supplierInvoice->refresh()->status);
    }

    public function test_supplier_invoice_with_ocr_failure_can_be_manually_verified_and_submitted(): void
    {
        Storage::fake('local');
        $this->fakeFailingTesseractExtractor();

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Manual verification fallback approved by procurement lead.',
            'external_reference' => 'MANUAL-VERIFY-001',
            'description' => 'Supplier invoice with unreadable OCR scan',
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->uploadSupplierInvoiceCopy($supplierInvoice);
        $extraction = AttachmentExtraction::where('document_id', $supplierInvoice->id)->firstOrFail();
        $this->assertSame('failed', $extraction->status);

        $salesUser = User::factory()->create([
            'role' => 'sales',
            'is_active' => true,
        ]);

        $this->actingAs($salesUser)
            ->put(route('documents.supplier-invoice-verification.verify', $supplierInvoice), $this->manualVerificationPayload([
                'fields' => [
                    'invoice_number' => 'MANUAL-VERIFY-001',
                    'invoice_date' => '2026-05-14',
                    'total' => '486.00',
                ],
            ]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('documents.supplier-invoice-verification.verify', $supplierInvoice), $this->manualVerificationPayload([
                'fields' => [
                    'invoice_number' => 'MANUAL-VERIFY-001',
                    'invoice_date' => '2026-05-14',
                    'total' => '486.00',
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Supplier invoice details verified.');

        $extraction->refresh();
        $this->assertSame('verified', $extraction->status);
        $this->assertSame('manual', $extraction->verification_method);
        $this->assertTrue($extraction->supplier_confirmed);
        $this->assertTrue($extraction->recorded_total_confirmed);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'supplier_invoice_details_verified',
            'auditable_type' => AttachmentExtraction::class,
            'auditable_id' => $extraction->id,
        ]);

        $this->post(route('documents.submit', $supplierInvoice))
            ->assertRedirect();
        $this->assertSame('pending_approval', $supplierInvoice->refresh()->status);
    }

    public function test_supplier_invoice_matching_requires_verified_details(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Direct supplier invoice waiting for verification before matching.',
            'external_reference' => 'MATCH-UNVERIFIED-001',
            'description' => 'Unverified supplier invoice should not match',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->uploadSupplierInvoiceCopy($supplierInvoice);
        $supplierInvoice->update(['status' => 'approved']);

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Supplier invoice matching checklist');
        $show->assertSee('Blocked');
        $show->assertSee('Invoice details verified');
        $show->assertDontSee('>Match supplier invoice</button>', false);

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.transition', [$supplierInvoice, 'match']))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('matching');
        $this->assertSame('approved', $supplierInvoice->refresh()->status);
    }

    public function test_supplier_invoice_matching_blocks_missing_source_supplier_mismatch_and_duplicate_invoice_number(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $missingSourceInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'MATCH-MISSING-SOURCE',
            'description' => 'Supplier invoice missing source document',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->uploadSupplierInvoiceCopy($missingSourceInvoice);
        $this->verifySupplierInvoiceManually($missingSourceInvoice, [
            'fields' => [
                'invoice_number' => 'MATCH-MISSING-SOURCE',
                'invoice_date' => '2026-05-14',
                'total' => '486.00',
            ],
        ]);
        $missingSourceInvoice->update(['status' => 'approved']);

        $this->from(route('documents.show', $missingSourceInvoice))
            ->post(route('documents.transition', [$missingSourceInvoice, 'match']))
            ->assertRedirect(route('documents.show', $missingSourceInvoice))
            ->assertSessionHasErrors('matching');

        $otherSupplier = Supplier::create([
            'name' => 'Mismatch Supplier Sdn Bhd',
            'code' => 'MIS',
            'category' => 'Materials / Hardware',
            'email' => 'accounts@mismatch.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
        $otherSupplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $otherSupplier->id,
            'external_reference' => 'MATCH-MISMATCH-SPO',
            'description' => 'Source PO for another supplier',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($otherSupplierPo);
        $this->transition($otherSupplierPo, 'issue', 'issued');

        $supplierMismatchInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'MATCH-SUPPLIER-MISMATCH',
            'description' => 'Supplier invoice linked to mismatched PO',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $supplierMismatchInvoice->update(['related_document_id' => $otherSupplierPo->id]);
        $this->uploadSupplierInvoiceCopy($supplierMismatchInvoice);
        $this->verifySupplierInvoiceManually($supplierMismatchInvoice, [
            'fields' => [
                'invoice_number' => 'MATCH-SUPPLIER-MISMATCH',
                'invoice_date' => '2026-05-14',
                'total' => '486.00',
            ],
        ]);
        $supplierMismatchInvoice->update(['status' => 'approved']);

        $this->from(route('documents.show', $supplierMismatchInvoice))
            ->post(route('documents.transition', [$supplierMismatchInvoice, 'match']))
            ->assertRedirect(route('documents.show', $supplierMismatchInvoice))
            ->assertSessionHasErrors('matching');

        $firstDuplicate = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'First invoice with this supplier invoice number.',
            'external_reference' => 'DUP-SUPPLIER-INV',
            'description' => 'First duplicate invoice number',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->uploadSupplierInvoiceCopy($firstDuplicate);
        $this->verifySupplierInvoiceManually($firstDuplicate, [
            'fields' => [
                'invoice_number' => 'DUP-SUPPLIER-INV',
                'invoice_date' => '2026-05-14',
                'total' => '486.00',
            ],
        ]);

        $secondDuplicate = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'source_type' => 'direct_supplier_invoice',
            'source_note' => 'Second invoice should be blocked by duplicate number check.',
            'external_reference' => 'DUP-SUPPLIER-INV',
            'description' => 'Second duplicate invoice number',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->uploadSupplierInvoiceCopy($secondDuplicate);
        $this->verifySupplierInvoiceManually($secondDuplicate, [
            'fields' => [
                'invoice_number' => 'DUP-SUPPLIER-INV',
                'invoice_date' => '2026-05-14',
                'total' => '486.00',
            ],
        ]);
        $secondDuplicate->update(['status' => 'approved']);

        $this->from(route('documents.show', $secondDuplicate))
            ->post(route('documents.transition', [$secondDuplicate, 'match']))
            ->assertRedirect(route('documents.show', $secondDuplicate))
            ->assertSessionHasErrors('matching');
    }

    public function test_supplier_invoice_can_be_matched_when_checklist_passes(): void
    {
        Storage::fake('local');

        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'MATCH-PASS-SPO',
            'description' => 'Issued PO for matching pass test',
            'quantity' => 2,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $goodsReceipt = $this->createDocument('goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'MATCH-PASS-GR',
            'description' => 'Received goods for matching pass test',
            'quantity' => 2,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($goodsReceipt);
        $this->transition($goodsReceipt, 'receive', 'received');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $goodsReceipt->id,
            'source_type' => 'goods_receipt',
            'external_reference' => 'MATCH-PASS-SIN',
            'description' => 'Supplier invoice with passing checklist',
            'quantity' => 2,
            'unit_price' => 450,
        ]);
        $this->uploadAndVerifySupplierInvoiceExtraction($supplierInvoice, [
            'invoice_number' => 'MATCH-PASS-SIN',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '900.00',
            'tax_total' => '72.00',
            'total' => '972.00',
            'payment_terms' => '30 days from invoice date',
        ]);
        $this->submitAndApprove($supplierInvoice);

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Supplier invoice matching checklist');
        $show->assertSee('Passed');
        $show->assertSee('Invoice details verified');
        $show->assertSee('>Match supplier invoice</button>', false);

        $this->transition($supplierInvoice, 'match', 'matched');
    }

    public function test_supplier_invoice_amount_tolerance_allows_exact_rounding_and_soft_variance(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $exact = $this->createApprovedSupplierInvoiceForAmountCheck('AMOUNT-EXACT', 1000, 1000);
        $exactShow = $this->get(route('documents.show', $exact));
        $exactShow->assertOk();
        $exactShow->assertSee('Invoice total matches the purchase order amount exactly.');
        $this->transition($exact, 'match', 'matched');

        $rounding = $this->createApprovedSupplierInvoiceForAmountCheck('AMOUNT-ROUNDING', 1000, 1000.009);
        $roundingShow = $this->get(route('documents.show', $rounding));
        $roundingShow->assertOk();
        $roundingShow->assertSee('Invoice total is within the MYR 0.01 rounding tolerance for the purchase order amount.');
        $this->transition($rounding, 'match', 'matched');

        $soft = $this->createApprovedSupplierInvoiceForAmountCheck('AMOUNT-SOFT', 1000, 1000.463);
        $softShow = $this->get(route('documents.show', $soft));
        $softShow->assertOk();
        $softShow->assertSee('Invoice total variance of MYR 0.50 is within the soft tolerance of MYR 1.00 for the purchase order amount.');
        $this->transition($soft, 'match', 'matched');
    }

    public function test_supplier_invoice_amount_variance_above_soft_tolerance_fails_for_normal_users(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $supplierInvoice = $this->createApprovedSupplierInvoiceForAmountCheck('AMOUNT-ABOVE-SOFT', 1000, 1001);

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Invoice total variance of MYR 1.08 exceeds the soft tolerance of MYR 1.00.');
        $show->assertSee('Match with audited override');
        $show->assertDontSee('>Match supplier invoice</button>', false);

        $procurementUser = User::factory()->create([
            'role' => 'procurement',
            'is_active' => true,
        ]);

        $this->actingAs($procurementUser)
            ->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.transition', [$supplierInvoice, 'match']))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('matching');

        $this->actingAs($procurementUser)
            ->post(route('documents.transition', [$supplierInvoice, 'match']), [
                'matching_override_reason' => 'Procurement accepts the price difference.',
            ])
            ->assertForbidden();

        $this->assertSame('approved', $supplierInvoice->refresh()->status);
    }

    public function test_partial_supplier_invoice_amount_requires_explicit_override_when_partial_matching_is_not_supported(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $supplierInvoice = $this->createApprovedSupplierInvoiceForAmountCheck('AMOUNT-PARTIAL', 1000, 500);

        $show = $this->get(route('documents.show', $supplierInvoice));
        $show->assertOk();
        $show->assertSee('Partial supplier invoice amount differs from the purchase order by MYR 540.00.');
        $show->assertSee('Partial matching is not treated as a normal match');
        $show->assertDontSee('>Match supplier invoice</button>', false);

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.transition', [$supplierInvoice, 'match']))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('matching');

        $this->post(route('documents.transition', [$supplierInvoice, 'match']), [
            'matching_override_reason' => 'Manager accepted this as a partial supplier invoice against the PO.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Matched recorded with audited override.');

        $this->assertSame('matched', $supplierInvoice->refresh()->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'supplier_invoice_match_override',
            'auditable_type' => Document::class,
            'auditable_id' => $supplierInvoice->id,
        ]);
    }

    public function test_supplier_invoice_matching_override_requires_reason_authority_and_audit_trail(): void
    {
        Storage::fake('local');
        config(['ocr.enabled' => false]);

        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'MATCH-OVERRIDE-SPO',
            'description' => 'Issued PO with lower amount',
            'quantity' => 1,
            'unit_price' => 450,
        ]);
        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => 'MATCH-OVERRIDE-SIN',
            'description' => 'Supplier invoice with approved amount difference',
            'quantity' => 1,
            'unit_price' => 500,
        ]);
        $this->uploadSupplierInvoiceCopy($supplierInvoice);
        $this->verifySupplierInvoiceManually($supplierInvoice, [
            'fields' => [
                'invoice_number' => 'MATCH-OVERRIDE-SIN',
                'invoice_date' => '2026-05-14',
                'total' => '540.00',
            ],
        ]);
        $supplierInvoice->update(['status' => 'approved']);

        $this->from(route('documents.show', $supplierInvoice))
            ->post(route('documents.transition', [$supplierInvoice, 'match']))
            ->assertRedirect(route('documents.show', $supplierInvoice))
            ->assertSessionHasErrors('matching');

        $procurementUser = User::factory()->create([
            'role' => 'procurement',
            'is_active' => true,
        ]);

        $this->actingAs($procurementUser)
            ->post(route('documents.transition', [$supplierInvoice, 'match']), [
                'matching_override_reason' => 'Supplier confirmed accepted variance by email.',
            ])
            ->assertForbidden();
        $this->assertSame('approved', $supplierInvoice->refresh()->status);

        $this->actingAs($this->admin)
            ->post(route('documents.transition', [$supplierInvoice, 'match']), [
                'matching_override_reason' => 'Manager accepted supplier price variance against the PO.',
            ])
            ->assertRedirect();

        $this->assertSame('matched', $supplierInvoice->refresh()->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'supplier_invoice_match_override',
            'auditable_type' => Document::class,
            'auditable_id' => $supplierInvoice->id,
        ]);
    }

    public function test_document_form_uses_document_level_tax_input_not_line_item_tax(): void
    {
        $response = $this->get(route('documents.create', 'customer-invoices'));

        $response->assertOk();
        $response->assertSee('Tax rate for totals (%)');
        $response->assertSee('name="document_tax_rate"', false);
        $response->assertDontSee('Tax %');
        $response->assertDontSee('data-name="tax_rate"', false);
        $response->assertSee('Select billing basis');
        $response->assertSee('Customer PO');
        $response->assertSee('Progress claim');
        $response->assertSee('Direct invoice');
        $response->assertSee('No recorded PO. Add a reason.');
        $response->assertSee('Document readiness');
        $response->assertSee('data-form-readiness-summary', false);
        $response->assertSee('value="customer_po"', false);
        $response->assertSee('value="progress_claim"', false);
        $response->assertSee('value="direct_invoice"', false);
        $response->assertSee('Document form sections');
        $response->assertSee('Customer and invoice details');
        $response->assertSee('Payment terms');
        $response->assertDontSee('Party and dates');
        $response->assertDontSee('Money and terms');
    }

    public function test_quotation_create_page_uses_quotation_workflow_language_and_autofill_hooks(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TEST-QUOTE-RELATED',
            'description' => 'Related quotation option',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $invoice = $this->createDocument('customer-invoices', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TEST-INVOICE-NOT-QUOTE-RELATED',
            'description' => 'Invoice should not appear as quotation source',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $response = $this->get(route('documents.create', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('Customer inquiry / reference');
        $response->assertSee('Previous quotation / revision');
        $response->assertSee('Valid until');
        $response->assertDontSee('Due date');
        $response->assertSee('Customer request');
        $response->assertSee('Customer and quotation details');
        $response->assertSee('Pricing and terms');
        $response->assertSee('Quoted items');
        $response->assertSee('Scope and terms');
        $response->assertDontSee('Source path');
        $response->assertDontSee('Party and dates');
        $response->assertDontSee('Money and terms');
        $response->assertSee('Document form sections');
        $response->assertSee('Document readiness');
        $response->assertSee('Simple payment terms');
        $response->assertSee('Project scope summary');
        $response->assertSee('data-billing-stage-panel', false);
        $response->assertSee('hidden', false);
        $response->assertSee('data-summary-total', false);
        $response->assertSee('data-description=', false);
        $response->assertSee('data-related-document-select', false);
        $response->assertSee('data-quotation-form-workspace', false);
        $response->assertSee('data-quotation-live-preview-pane', false);
        $response->assertSee('aria-label="Customer quotation preview"', false);
        $response->assertSee('data-live-quotation-preview', false);
        $response->assertSee('data-preview-lines', false);
        $response->assertSee('data-preview-total', false);
        $response->assertDontSee('Live document preview');
        $response->assertDontSee('This is what the customer will review after the quotation is saved or exported to PDF.');
        $response->assertSee('data-party-id="'.$this->customer->id.'"', false);
        $response->assertSee($quotation->document_number);
        $response->assertDontSee($invoice->document_number);
    }

    public function test_supplier_quotation_create_page_uses_supplier_procurement_language(): void
    {
        $response = $this->get(route('documents.create', 'supplier-quotations'));

        $response->assertOk();
        $response->assertSee('New supplier quotation');
        $response->assertSee('Record supplier pricing, validity, and terms before PO.');
        $response->assertSee('Quotation basis');
        $response->assertSee('What is this supplier quotation for?');
        $response->assertSee('Link to a purchase request or earlier supplier quotation.');
        $response->assertSee('Purchase request');
        $response->assertSee('Previous quotation');
        $response->assertSee('Supplier and quotation details');
        $response->assertSee('Supplier quotation no. / reference');
        $response->assertSee('Pricing and terms');
        $response->assertSee('Payment terms text');
        $response->assertSee('Quoted items');
        $response->assertSee('Supplier notes and terms');
        $response->assertSee('Record supplier remarks, exclusions, and terms.');
        $response->assertSee('Supplier quotation summary');
        $response->assertSee('Supplier quotation reference');
        $response->assertSee('Supplier quotation summary');
        $response->assertDontSee('Customer inquiry / reference');
        $response->assertDontSee('customer-facing scope');
        $response->assertDontSee('Enter the quote, terms, items, and scope. The preview updates as you work.');
        $response->assertDontSee('Payment term label');
        $response->assertDontSee('Source path');
        $response->assertDontSee('Party and dates');
        $response->assertDontSee('Money and terms');
    }

    public function test_document_studio_forms_keep_source_values_and_required_guidance(): void
    {
        $modules = [
            'customer-pos' => ['quotation', 'direct_customer_po'],
            'customer-invoices' => ['customer_po', 'progress_claim', 'direct_invoice'],
            'purchase-requests' => ['supplier_quote', 'quote_exception'],
            'supplier-quotations' => ['purchase_request', 'supplier_quote'],
            'supplier-pos' => ['supplier_quote', 'purchase_request', 'direct_supplier_po'],
            'goods-receipts' => ['supplier_po', 'direct_receipt'],
            'supplier-invoices' => ['goods_receipt', 'supplier_po', 'direct_supplier_invoice'],
        ];

        foreach ($modules as $module => $sourceValues) {
            $response = $this->get(route('documents.create', $module));

            $response->assertOk();
            $response->assertSee('Document readiness');
            $response->assertSee('data-form-readiness-summary', false);

            foreach ($sourceValues as $sourceValue) {
                $response->assertSee('value="'.$sourceValue.'"', false);
            }
        }

        $supplierInvoiceForm = $this->get(route('documents.create', 'supplier-invoices'));
        $supplierInvoiceForm->assertSee('No PO or receipt required. Add a reason.');
        $supplierInvoiceForm->assertSee('Required for direct supplier invoice. Explain why no PO or receipt is required.');

        $goodsReceiptForm = $this->get(route('documents.create', 'goods-receipts'));
        $goodsReceiptForm->assertSee('No PO yet. Add a reason.');
        $goodsReceiptForm->assertSee('Required for direct receipt. Normal receiving must use an issued purchase order.');
    }

    public function test_document_form_validation_errors_remain_visible_after_redirect(): void
    {
        $payload = $this->documentPayload('customer-invoices', [
            'customer_id' => $this->customer->id,
            'source_type' => 'direct_invoice',
            'external_reference' => 'FORM-DIRECT-NO-REASON',
            'description' => 'Direct invoice without reason',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);
        unset($payload['source_note']);

        $this->followingRedirects()
            ->from(route('documents.create', 'customer-invoices'))
            ->post(route('documents.store', 'customer-invoices'), $payload)
            ->assertOk()
            ->assertSee('Please fix the highlighted fields.')
            ->assertSee('Add a reason for this direct customer invoice.')
            ->assertSee('Document readiness');
    }

    public function test_customer_quotation_listing_embeds_preview_for_each_visible_quote(): void
    {
        $firstQuotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'PREVIEW-LIST-001',
            'description' => 'First embedded quotation preview item',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $secondQuotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'PREVIEW-LIST-002',
            'description' => 'Second embedded quotation preview item',
            'quantity' => 2,
            'unit_price' => 750,
        ]);

        $response = $this->get(route('documents.index', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('Customer quotation PDF');
        $response->assertSee('data-quotation-workspace', false);
        $response->assertSee('data-quotation-list', false);
        $response->assertSee('data-quotation-preview-list', false);
        $response->assertSee('data-quotation-preview-panel', false);
        $response->assertSee('data-quotation-row', false);
        $response->assertSee('data-preview-card-wrapper', false);
        $response->assertSee('class="sr-only"', false);
        $response->assertDontSee('document-preview-toolbar', false);
        $response->assertDontSee('Preview every quotation');
        $response->assertDontSee('Embedded Quotation Previews');
        $response->assertSee('data-generated-pdf-preview', false);
        $response->assertSee('Customer quotation PDF');
        $response->assertSee('Draft quotation preview');
        $response->assertSee(route('documents.pdf', $firstQuotation), false);
        $response->assertSee(route('documents.pdf', $secondQuotation), false);
        $response->assertSee($firstQuotation->document_number);
        $response->assertSee($secondQuotation->document_number);
    }

    public function test_document_index_exposes_separate_preview_and_open_actions(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'ROW-A11Y-QUOTE',
            'description' => 'Quotation used to check row actions',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $response = $this->get(route('documents.index', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('data-document-preview-disclosure', false);
        $response->assertSee('Selected document preview');
        $response->assertSee('1 record');
        $response->assertDontSee('1 records');
        $response->assertSee('aria-label="Filter customer quotations"', false);
        $response->assertSee('aria-label="Search customer quotations"', false);
        $response->assertSee('aria-label="Filter by status"', false);
        $response->assertSee('aria-label="From date"', false);
        $response->assertSee('aria-label="To date"', false);
        $response->assertSee('data-preview-trigger', false);
        $response->assertSee('aria-controls="document-preview-'.$quotation->id.'"', false);
        $response->assertSee('aria-current="true"', false);
        $response->assertSee('aria-pressed="true"', false);
        $response->assertSee('aria-label="Preview shown for '.$quotation->document_number.'"', false);
        $response->assertSee('>Preview shown</button>', false);
        $response->assertSee('aria-label="Open '.$quotation->document_number.' details"', false);
        $response->assertSee('aria-label="Status: Draft. Draft status."', false);
        $response->assertSee('data-status-group="Draft status"', false);
        $response->assertSee('href="'.route('documents.show', $quotation).'"', false);
        $response->assertDontSee('role="button"', false);
    }

    public function test_search_matches_documents_customers_suppliers_and_items(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'SEARCH-ACME-QUOTE',
            'description' => 'Customer search scope with consulting package',
            'quantity' => 1,
            'unit_price' => 1200,
        ]);

        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'SEARCH-BEST-SUPPLIER',
            'description' => 'Supplier search scope with consulting package',
            'quantity' => 2,
            'unit_price' => 450,
        ]);

        $acceptedPo = $this->createDocument('customer-pos', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'SEARCH-ACCEPTED-PO',
            'description' => 'Accepted customer PO search record',
            'quantity' => 1,
            'unit_price' => 300,
        ]);
        $acceptedPo->update(['status' => 'issued']);

        $quotation->update([
            'project_name' => 'Cyberjaya Site',
            'payment_terms_type' => 'milestone',
            'payment_terms_label' => 'Milestone based',
            'payment_due_days' => 30,
        ]);

        $quotation->billingStages()->create([
            'sort_order' => 1,
            'stage_name' => 'Deposit',
            'condition_label' => 'Upon customer PO / written acceptance',
            'percentage' => 40,
            'amount' => 518.40,
            'payment_term' => 'Due upon invoice',
        ]);

        $this->get(route('search.index', ['q' => 'Acme Trading']))
            ->assertOk()
            ->assertSee('Search Suria QuoteFlow')
            ->assertSee($quotation->document_number)
            ->assertSee('Acme Trading Sdn Bhd');

        $this->get(route('search.index', ['q' => 'Best Supplies']))
            ->assertOk()
            ->assertSee($supplierPo->document_number)
            ->assertSee('Best Supplies Sdn Bhd');

        $this->get(route('search.index', ['q' => 'Materials / Hardware']))
            ->assertOk()
            ->assertSee($supplierPo->document_number)
            ->assertSee('Materials / Hardware');

        $this->get(route('search.index', ['q' => 'CONSULT']))
            ->assertOk()
            ->assertSee('Consulting package')
            ->assertSee($quotation->document_number);

        $this->get(route('search.index', ['q' => $quotation->document_number]))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertDontSee($supplierPo->document_number);

        $this->get(route('search.index', ['q' => 'Cyberjaya Site']))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertSee('Cyberjaya Site');

        $this->get(route('search.index', ['q' => 'Deposit']))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertSee('Deposit');

        $this->get(route('search.index', ['q' => 'Milestone based']))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertSee('Milestone based');

        $this->get(route('search.index', ['q' => '30 days']))
            ->assertOk()
            ->assertSee('Default term: 30 days');

        $this->get(route('search.index', ['q' => 'Draft']))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertSee($supplierPo->document_number)
            ->assertSee('Draft');

        $this->get(route('search.index', ['q' => 'MYR 1,296']))
            ->assertOk()
            ->assertSee($quotation->document_number)
            ->assertSee('MYR 1,296.00');

        $this->get(route('search.index', ['q' => 'Purchase Order']))
            ->assertOk()
            ->assertSee($supplierPo->document_number)
            ->assertSee('Purchase order');

        $this->get(route('search.index', ['q' => 'Accepted']))
            ->assertOk()
            ->assertSee($acceptedPo->document_number)
            ->assertSee('Accepted');

        $this->get(route('documents.index', ['module' => 'customer-quotations', 'q' => 'Acme Trading']))
            ->assertOk()
            ->assertSee($quotation->document_number);

        $this->get(route('documents.index', ['module' => 'supplier-pos', 'q' => 'Best Supplies']))
            ->assertOk()
            ->assertSee($supplierPo->document_number);
    }

    public function test_company_profile_controls_company_identity(): void
    {
        $company = CompanyProfile::create([
            'name' => 'Suria Energy Services Sdn Bhd',
            'registration_number' => 'SUB-2026-001',
            'email' => 'admin@suria-energy.test',
            'phone' => '+60 3-1234 5678',
            'address' => 'Level 8, Subsidiary Tower, Kuala Lumpur',
            'tagline' => 'Subsidiary commercial operations.',
            'primary_color' => '#102a43',
            'accent_color' => '#0a4f93',
            'is_active' => true,
        ]);

        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'COMPANY-BRAND-QUOTE',
            'description' => 'Company identity quotation output',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $response = $this->get(route('documents.index', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('Suria QuoteFlow');
        $response->assertSee('Suria Energy Services Sdn Bhd');
        $response->assertSee('Customer quotation PDF');
        $response->assertSee(route('documents.pdf', $quotation), false);

        $identityResponse = $this->get(route('company-profiles.index'));

        $identityResponse->assertOk();
        $identityResponse->assertSee('Document issuer profile');
        $identityResponse->assertSee('Current document issuer');
        $identityResponse->assertSee('Edit company profile');
        $identityResponse->assertDontSee('Add company profile');
        $identityResponse->assertDontSee('Active company profile');
        $identityResponse->assertDontSee('Set as the active company profile');

        $this->get(route('company-profiles.create'))
            ->assertRedirect(route('company-profiles.edit', $company));

        $companyCount = CompanyProfile::count();

        $this->post(route('company-profiles.store'), [
            'name' => 'Suria Energy Services Sdn Bhd',
            'registration_number' => 'SUB-2026-001',
            'email' => 'admin@suria-energy.test',
            'phone' => '+60 3-1234 5678',
            'address' => 'Level 8, Subsidiary Tower, Kuala Lumpur',
            'tagline' => 'Subsidiary commercial operations.',
            'primary_color' => '#102a43',
            'accent_color' => '#0a4f93',
        ])->assertRedirect(route('company-profiles.index'));

        $this->assertSame($companyCount, CompanyProfile::count());

        $html = view('documents.pdf', [
            'document' => $quotation->load(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments', 'creator', 'approver']),
            'meta' => Document::metaForSlug(Document::slugForType($quotation->type)),
        ])->render();

        $this->assertStringContainsString('SURIA ENERGY SERVICES SDN BHD', $html);
        $this->assertStringContainsString('admin@suria-energy.test', $html);
        $this->assertSame($company->id, CompanyProfile::active()->id);
    }

    public function test_customer_quotation_rejects_previous_quotation_from_another_customer(): void
    {
        $otherCustomer = Customer::create([
            'name' => 'Global Enterprises Sdn Bhd',
            'code' => 'GLOBAL',
            'email' => 'accounts@global.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $otherQuotation = $this->createDocument('customer-quotations', [
            'customer_id' => $otherCustomer->id,
            'external_reference' => 'OTHER-CUSTOMER-QUOTE',
            'description' => 'Other customer quotation',
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $response = $this->post(route('documents.store', 'customer-quotations'), [
            'customer_id' => $this->customer->id,
            'related_document_id' => $otherQuotation->id,
            'external_reference' => 'BAD-RELATED-CUSTOMER',
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Quotation should fail related customer validation',
                    'quantity' => 1,
                    'unit' => 'job',
                    'unit_price' => 1200,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('related_document_id');
        $this->assertDatabaseMissing('documents', [
            'external_reference' => 'BAD-RELATED-CUSTOMER',
        ]);
    }

    public function test_quotation_pdf_uses_scope_summary_without_line_item_tax(): void
    {
        $quotation = $this->createDocument('customer-quotations', [
            'customer_id' => $this->customer->id,
            'external_reference' => 'TEST-QUOTE-SCOPE',
            'description' => 'Telecommunications installation support',
            'quantity' => 2,
            'unit_price' => 1200,
        ]);

        $html = view('documents.pdf', [
            'document' => $quotation->load(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments', 'creator', 'approver']),
            'meta' => Document::metaForSlug(Document::slugForType($quotation->type)),
        ])->render();

        $this->assertStringContainsString('<h1>QUOTATION</h1>', $html);
        $this->assertStringContainsString('Project Scope Summary', $html);
        $this->assertStringContainsString('Feature test workflow document.', $html);
        $this->assertStringNotContainsString('<th class="right" style="width: 62px;">Tax</th>', $html);
    }

    public function test_milestone_billing_inputs_drive_customer_invoice_output(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'external_reference' => 'CPO-MILESTONE-001',
            'project_name' => 'Cyberjaya Site',
            'delivery_to' => "Cyberjaya, Selangor\nContact: Operations Lead",
            'issue_date' => '2026-05-14',
            'currency' => 'MYR',
            'payment_terms_type' => 'milestone',
            'payment_due_days' => 7,
            'progress_invoice_number' => 2,
            'progress_invoice_total' => 3,
            'billing_stage_name' => 'Progress Claim 1',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Progress Claim 1 - installation start',
                    'quantity' => 1,
                    'unit' => 'stage',
                    'unit_price' => 1100,
                    'tax_rate' => 8,
                ],
            ],
            'billing_stages' => [
                [
                    'stage_name' => 'Deposit',
                    'condition_label' => 'Upon customer PO / written acceptance',
                    'percentage' => 40,
                    'amount' => 1188,
                    'payment_term' => 'Due upon invoice',
                    'previously_invoiced' => 1188,
                    'current_invoice' => 0,
                    'remaining_amount' => 0,
                    'is_current' => 0,
                ],
                [
                    'stage_name' => 'Progress Claim 1',
                    'condition_label' => 'Upon delivery or installation start',
                    'percentage' => 40,
                    'amount' => 1188,
                    'payment_term' => '7 days from invoice date',
                    'previously_invoiced' => 0,
                    'current_invoice' => 1188,
                    'remaining_amount' => 0,
                    'is_current' => 1,
                ],
                [
                    'stage_name' => 'Final Claim',
                    'condition_label' => 'Upon completion and handover acceptance',
                    'percentage' => 20,
                    'amount' => 594,
                    'payment_term' => '14 days from invoice date',
                    'previously_invoiced' => 0,
                    'current_invoice' => 0,
                    'remaining_amount' => 594,
                    'is_current' => 0,
                ],
            ],
            'notes' => 'Progress billing test.',
            'terms' => 'Customer-facing progress billing terms.',
        ];

        $this->post(route('documents.store', 'customer-invoices'), $payload)
            ->assertRedirect();

        $invoice = Document::where('external_reference', 'CPO-MILESTONE-001')->firstOrFail();

        $this->assertSame('2026-05-21', $invoice->due_date->format('Y-m-d'));
        $this->assertSame('milestone', $invoice->payment_terms_type);
        $this->assertSame(7, $invoice->payment_due_days);
        $this->assertSame('7 days from invoice date', $invoice->payment_terms_label);
        $this->assertSame(2, $invoice->progress_invoice_number);
        $this->assertSame(3, $invoice->progress_invoice_total);
        $this->assertSame('Progress Claim 1', $invoice->billing_stage_name);
        $this->assertSame('1188.00', $invoice->total);
        $this->assertCount(3, $invoice->billingStages);

        $html = view('documents.pdf', [
            'document' => $invoice->load(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments', 'creator', 'approver']),
            'meta' => Document::metaForSlug(Document::slugForType($invoice->type)),
        ])->render();

        $this->assertStringContainsString('Progress Billing Summary', $html);
        $this->assertStringContainsString('<h1>INVOICE</h1>', $html);
        $this->assertStringContainsString('No. 2 of 3', $html);
        $this->assertStringContainsString('Previously Invoiced', $html);
        $this->assertStringContainsString('This Invoice', $html);
        $this->assertStringContainsString('Remaining To Invoice', $html);
        $this->assertStringContainsString('7 days from invoice date', $html);
        $this->assertStringNotContainsString('Tax Invoice', $html);
        $this->assertStringNotContainsString('Invoice for Services', $html);
        $this->assertStringNotContainsString('<th class="right" style="width: 62px;">Tax</th>', $html);
    }

    public function test_supplier_po_uses_supplier_facing_payment_conditions(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'external_reference' => 'SQ-MILESTONE-001',
            'project_name' => 'Cyberjaya Site',
            'delivery_to' => "RCTech Project Site\nCyberjaya, Selangor",
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'payment_terms_type' => 'milestone',
            'payment_terms_label' => 'Milestone based',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Operations hardware kit',
                    'quantity' => 5,
                    'unit' => 'set',
                    'unit_price' => 450,
                    'tax_rate' => 8,
                ],
            ],
            'billing_stages' => [
                [
                    'stage_name' => 'Deposit',
                    'condition_label' => 'PO acknowledged by supplier',
                    'percentage' => 40,
                    'amount' => 1080,
                    'payment_term' => 'Due upon receipt of valid invoice',
                ],
                [
                    'stage_name' => 'Progress Claim 1',
                    'condition_label' => 'Delivery readiness / site mobilization accepted by RCTech',
                    'percentage' => 40,
                    'amount' => 1080,
                    'payment_term' => '7 days from receipt of valid invoice',
                ],
            ],
        ];

        $this->post(route('documents.store', 'supplier-pos'), $payload)
            ->assertRedirect();

        $supplierPo = Document::where('external_reference', 'SQ-MILESTONE-001')->firstOrFail();
        $this->assertCount(2, $supplierPo->billingStages);

        $html = view('documents.pdf', [
            'document' => $supplierPo->load(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments', 'creator', 'approver']),
            'meta' => Document::metaForSlug(Document::slugForType($supplierPo->type)),
        ])->render();

        $this->assertStringContainsString('Supplier May Invoice When', $html);
        $this->assertStringContainsString('valid invoice', $html);
        $this->assertStringContainsString('accepted by RCTech', $html);
        $this->assertStringNotContainsString('actual due date', $html);
        $this->assertStringNotContainsString('trigger', strtolower($html));
    }

    private function createDocument(string $module, array $overrides): Document
    {
        $reference = $overrides['external_reference'];

        $payload = $this->documentPayload($module, $overrides);

        if (($payload['source_type'] ?? null) === 'direct_receipt' && empty($payload['source_note'])) {
            $payload['source_note'] = 'Direct receipt exception for feature test.';
        }

        if ($module === 'purchase-requests' && empty($payload['source_type'])) {
            $payload['source_type'] = 'quote_exception';
            $payload['source_note'] = 'Feature test quotation exception for non-OCR purchase request coverage.';
        }

        $this->post(route('documents.store', $module), $payload)
            ->assertRedirect();

        $document = Document::where('external_reference', $reference)->firstOrFail();

        $this->assertSame('draft', $document->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'document_created',
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);

        return $document;
    }

    private function documentPayload(string $module, array $overrides): array
    {
        return [
            'customer_id' => $overrides['customer_id'] ?? null,
            'supplier_id' => $overrides['supplier_id'] ?? null,
            'related_document_id' => $overrides['related_document_id'] ?? null,
            'project_id' => $overrides['project_id'] ?? null,
            'source_type' => $overrides['source_type'] ?? ($module === 'goods-receipts' && empty($overrides['related_document_id']) ? 'direct_receipt' : null),
            'source_note' => $overrides['source_note'] ?? null,
            'external_reference' => $overrides['external_reference'],
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => $overrides['description'],
                    'quantity' => $overrides['quantity'],
                    'unit' => 'job',
                    'unit_price' => $overrides['unit_price'],
                    'tax_rate' => 8,
                ],
            ],
            'notes' => 'Feature test workflow document.',
            'terms' => 'Generated by automated workflow coverage.',
        ];
    }

    private function directExceptionCases(): array
    {
        return [
            [
                'module' => 'customer-pos',
                'overrides' => [
                    'customer_id' => $this->customer->id,
                    'source_type' => 'direct_customer_po',
                    'external_reference' => 'DIRECT-CPO-NOTE-REQUIRED',
                    'description' => 'Direct customer PO exception',
                    'quantity' => 1,
                    'unit_price' => 1200,
                ],
            ],
            [
                'module' => 'customer-invoices',
                'overrides' => [
                    'customer_id' => $this->customer->id,
                    'source_type' => 'direct_invoice',
                    'external_reference' => 'DIRECT-CINV-NOTE-REQUIRED',
                    'description' => 'Direct customer invoice exception',
                    'quantity' => 1,
                    'unit_price' => 1200,
                ],
            ],
            [
                'module' => 'supplier-pos',
                'overrides' => [
                    'supplier_id' => $this->supplier->id,
                    'source_type' => 'direct_supplier_po',
                    'external_reference' => 'DIRECT-SPO-NOTE-REQUIRED',
                    'description' => 'Direct supplier PO exception',
                    'quantity' => 1,
                    'unit_price' => 450,
                ],
            ],
            [
                'module' => 'goods-receipts',
                'overrides' => [
                    'supplier_id' => $this->supplier->id,
                    'source_type' => 'direct_receipt',
                    'external_reference' => 'DIRECT-GR-NOTE-REQUIRED',
                    'description' => 'Direct receipt exception',
                    'quantity' => 1,
                    'unit_price' => 450,
                ],
            ],
            [
                'module' => 'supplier-invoices',
                'overrides' => [
                    'supplier_id' => $this->supplier->id,
                    'source_type' => 'direct_supplier_invoice',
                    'external_reference' => 'DIRECT-SIN-NOTE-REQUIRED',
                    'description' => 'Direct supplier invoice exception',
                    'quantity' => 1,
                    'unit_price' => 450,
                ],
            ],
        ];
    }

    private function createPendingApprovalRecord(string $documentNumber, ?\DateTimeInterface $requestedAt = null): Document
    {
        $document = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => $documentNumber,
            'external_reference' => $documentNumber,
            'customer_id' => $this->customer->id,
            'status' => 'pending_approval',
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'subtotal' => 100,
            'tax_total' => 8,
            'total' => 108,
            'notes' => 'Pagination test pending approval.',
            'terms' => 'Generated by automated workflow coverage.',
            'created_by' => $this->admin->id,
        ]);

        $approval = Approval::create([
            'document_id' => $document->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        if ($requestedAt) {
            $approval->forceFill([
                'created_at' => $requestedAt,
                'updated_at' => $requestedAt,
            ])->save();
        }

        return $document;
    }

    private function submitForApproval(Document $document): void
    {
        $this->ensurePurchaseRequestQuoteEvidence($document);

        $this->post(route('documents.submit', $document))
            ->assertRedirect();

        $this->assertSame('pending_approval', $document->refresh()->status);
        $this->assertDatabaseHas('approvals', [
            'document_id' => $document->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
        ]);
    }

    private function submitAndApprove(Document $document): void
    {
        $this->submitForApproval($document);

        $this->post(route('documents.approve', $document), [
            'comment' => 'Approved by workflow feature test.',
        ])->assertRedirect();

        $this->assertSame('approved', $document->refresh()->status);

        $approval = Approval::where('document_id', $document->id)->firstOrFail();
        $this->assertSame('approved', $approval->status);
        $this->assertSame($this->admin->id, $approval->decided_by);
    }

    private function ensurePurchaseRequestQuoteEvidence(Document $document): void
    {
        if ($document->type !== 'purchase_request' || $document->source_type === 'quote_exception') {
            return;
        }

        $document->loadMissing('attachments.extraction');

        $hasVerifiedQuote = $document->attachments
            ->where('category', 'supplier_quote')
            ->contains(fn (Attachment $attachment) => $attachment->extraction?->status === 'verified');

        if ($hasVerifiedQuote) {
            return;
        }

        $attachment = $document->attachments
            ->firstWhere('category', 'supplier_quote')
            ?: Attachment::create([
                'document_id' => $document->id,
                'category' => 'supplier_quote',
                'original_name' => 'supplier-quote-'.$document->id.'.pdf',
                'path' => 'attachments/test/supplier-quote-'.$document->id.'.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'uploaded_by' => $this->admin->id,
            ]);

        AttachmentExtraction::updateOrCreate(
            ['attachment_id' => $attachment->id],
            [
                'document_id' => $document->id,
                'status' => 'verified',
                'engine' => 'feature-test',
                'language' => 'eng',
                'raw_text' => 'Feature test supplier quotation evidence.',
                'extracted_fields' => [
                    'supplier_name' => $document->supplier?->name,
                    'quote_number' => $document->external_reference,
                    'quote_date' => optional($document->issue_date)->toDateString(),
                    'total' => number_format((float) $document->total, 2, '.', ''),
                ],
                'verified_fields' => [
                    'supplier_name' => $document->supplier?->name,
                    'quote_number' => $document->external_reference,
                    'quote_date' => optional($document->issue_date)->toDateString(),
                    'total' => number_format((float) $document->total, 2, '.', ''),
                ],
                'verification_method' => 'manual',
                'verification_notes' => 'Feature test supplier quotation evidence.',
                'supplier_confirmed' => true,
                'recorded_total_confirmed' => true,
                'error_message' => null,
                'verified_by' => $this->admin->id,
                'verified_at' => now(),
            ]
        );
    }

    private function uploadAndVerifySupplierInvoiceExtraction(Document $document, array $fields): void
    {
        Storage::fake('local');
        $this->fakeTesseractExtractor($fields);

        $upload = UploadedFile::fake()->image('supplier-invoice.jpg', 900, 1200);
        $this->post(route('documents.attachments.store', $document), [
            'attachment' => $upload,
            'category' => 'invoice_copy',
        ])->assertRedirect();

        $attachment = Attachment::where('document_id', $document->id)
            ->where('category', 'invoice_copy')
            ->firstOrFail();
        $extraction = AttachmentExtraction::where('attachment_id', $attachment->id)->firstOrFail();

        $this->put(route('attachment-extractions.verify', $extraction), [
            'fields' => $fields,
            'supplier_confirmed' => '1',
            'recorded_total_confirmed' => '1',
            'verification_notes' => 'OCR-assisted details checked by feature test.',
        ])->assertRedirect();

        $this->assertSame('verified', $extraction->refresh()->status);
    }

    private function uploadSupplierInvoiceCopy(Document $document, string $filename = 'supplier-invoice.jpg'): Attachment
    {
        $upload = UploadedFile::fake()->image($filename, 900, 1200);
        $this->post(route('documents.attachments.store', $document), [
            'attachment' => $upload,
            'category' => 'invoice_copy',
        ])->assertRedirect();

        return Attachment::where('document_id', $document->id)
            ->where('category', 'invoice_copy')
            ->latest('id')
            ->firstOrFail();
    }

    private function verifySupplierInvoiceManually(Document $document, array $overrides = []): AttachmentExtraction
    {
        $this->put(route('documents.supplier-invoice-verification.verify', $document), $this->manualVerificationPayload($overrides))
            ->assertRedirect()
            ->assertSessionHas('status', 'Supplier invoice details verified.');

        return AttachmentExtraction::where('document_id', $document->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function createApprovedSupplierInvoiceForAmountCheck(string $reference, float $sourceUnitPrice, float $invoiceUnitPrice): Document
    {
        $supplierPo = $this->createDocument('supplier-pos', [
            'supplier_id' => $this->supplier->id,
            'external_reference' => $reference.'-SPO',
            'description' => 'Source purchase order for '.$reference,
            'quantity' => 1,
            'unit_price' => $sourceUnitPrice,
        ]);
        $this->submitAndApprove($supplierPo);
        $this->transition($supplierPo, 'issue', 'issued');

        $supplierInvoice = $this->createDocument('supplier-invoices', [
            'supplier_id' => $this->supplier->id,
            'related_document_id' => $supplierPo->id,
            'source_type' => 'supplier_po',
            'external_reference' => $reference,
            'description' => 'Supplier invoice amount check for '.$reference,
            'quantity' => 1,
            'unit_price' => $invoiceUnitPrice,
        ]);

        $this->uploadSupplierInvoiceCopy($supplierInvoice);
        $this->verifySupplierInvoiceManually($supplierInvoice, [
            'fields' => [
                'invoice_number' => $reference,
                'invoice_date' => '2026-05-14',
                'total' => number_format((float) $supplierInvoice->refresh()->total, 2, '.', ''),
            ],
        ]);
        $this->submitAndApprove($supplierInvoice);

        return $supplierInvoice->refresh();
    }

    private function manualVerificationPayload(array $overrides = []): array
    {
        $fields = array_merge([
            'supplier_name' => 'Best Supplies Sdn Bhd',
            'invoice_number' => 'MANUAL-SIN-001',
            'invoice_date' => '2026-05-14',
            'po_number' => 'SPO-REFERENCE',
            'subtotal' => '450.00',
            'tax_total' => '36.00',
            'total' => '486.00',
            'payment_terms' => '30 days from invoice date',
        ], $overrides['fields'] ?? []);

        return [
            'verification_method' => $overrides['verification_method'] ?? 'manual',
            'fields' => $fields,
            'supplier_confirmed' => $overrides['supplier_confirmed'] ?? '1',
            'recorded_total_confirmed' => $overrides['recorded_total_confirmed'] ?? '1',
            'verification_notes' => $overrides['verification_notes'] ?? 'Manual verification checked against uploaded invoice copy.',
        ];
    }

    private function fakeTesseractExtractor(array $fields, string $rawText = 'Supplier invoice OCR text'): void
    {
        config(['ocr.enabled' => true]);

        $this->app->instance(BusinessDocumentCaptureService::class, new class($fields, $rawText) extends BusinessDocumentCaptureService {
            public function __construct(private array $fields, private string $rawText)
            {
            }

            public function canExtract(Attachment $attachment): bool
            {
                return $attachment->canBeExtracted();
            }

            public function extract(Attachment $attachment): array
            {
                return [
                    'raw_text' => $this->rawText,
                    'extracted_fields' => $this->fields,
                    'engine' => 'tesseract',
                    'language' => 'eng',
                ];
            }
        });
    }

    private function fakeFailingTesseractExtractor(string $message = 'Tesseract could not read the scan.'): void
    {
        config(['ocr.enabled' => true]);

        $this->app->instance(BusinessDocumentCaptureService::class, new class($message) extends BusinessDocumentCaptureService {
            public function __construct(private string $message)
            {
            }

            public function canExtract(Attachment $attachment): bool
            {
                return $attachment->canBeExtracted();
            }

            public function extract(Attachment $attachment): array
            {
                throw new \RuntimeException($this->message);
            }
        });
    }

    private function transition(Document $document, string $action, string $expectedStatus): void
    {
        $this->post(route('documents.transition', [$document, $action]))
            ->assertRedirect();

        $this->assertSame($expectedStatus, $document->refresh()->status);
    }

    private function paymentPayload(Document $document, float $amount, string $reference): array
    {
        return [
            'payment_date' => '2026-05-14',
            'amount' => $amount,
            'method' => 'Bank transfer',
            'reference' => $reference,
            'notes' => 'Payment eligibility feature test.',
        ];
    }

    private function recordPayment(Document $document, string $expectedDirection): void
    {
        $this->post(route('payments.store', $document), [
            'payment_date' => '2026-05-14',
            'amount' => $document->refresh()->total,
            'method' => 'Bank transfer',
            'reference' => 'PAY-'.$document->document_number,
            'notes' => 'Full payment from workflow feature test.',
        ])->assertRedirect();

        $this->assertSame('paid', $document->refresh()->status);

        $payment = Payment::where('document_id', $document->id)->firstOrFail();
        $this->assertSame($expectedDirection, $payment->direction);
        $this->assertSame((float) $document->total, (float) $payment->amount);
    }
}

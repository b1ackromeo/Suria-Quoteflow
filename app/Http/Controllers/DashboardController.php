<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Supplier;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $receivableTotal = Document::where('type', 'customer_invoice')
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->sum('total');

        $receivablePaid = Payment::where('direction', 'incoming')->sum('amount');

        $payableTotal = Document::where('type', 'supplier_invoice')
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->sum('total');

        $payablePaid = Payment::where('direction', 'outgoing')->sum('amount');

        $documentCount = fn (string $type, array $statuses = []) => Document::where('type', $type)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->count();

        $openInvoiceQuery = fn () => Document::whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->whereNotIn('status', ['paid', 'closed', 'cancelled']);

        $today = now()->toDateString();

        return view('dashboard.index', [
            'cards' => [
                ['label' => 'Open Receivables', 'value' => $receivableTotal - $receivablePaid, 'note' => 'Customer invoices awaiting collection', 'accent' => 'primary', 'icon' => 'payments', 'trend' => 'Live balance'],
                ['label' => 'Open Payables', 'value' => $payableTotal - $payablePaid, 'note' => 'Supplier invoices awaiting payment', 'accent' => 'amber', 'icon' => 'purchase', 'trend' => 'Supplier side'],
                ['label' => 'Pending Approvals', 'value' => Approval::where('status', 'pending')->count(), 'plain' => true, 'note' => 'Documents waiting for manager action', 'accent' => 'blue', 'icon' => 'admin', 'trend' => 'Requires action'],
                ['label' => 'Trading Partners', 'value' => Customer::where('is_active', true)->count() + Supplier::where('is_active', true)->count(), 'plain' => true, 'note' => 'Active customers and suppliers', 'accent' => 'slate', 'icon' => 'customers', 'trend' => 'Master data'],
            ],
            'workflow' => [
                'outgoing' => [
                    ['label' => 'Quotations', 'count' => $documentCount('customer_quotation'), 'route' => route('documents.index', 'customer-quotations')],
                    ['label' => 'PO Received', 'count' => $documentCount('customer_po'), 'route' => route('documents.index', 'customer-pos')],
                    ['label' => 'Invoices', 'count' => $documentCount('customer_invoice'), 'route' => route('documents.index', 'customer-invoices')],
                    ['label' => 'Unpaid invoices', 'count' => $documentCount('customer_invoice', ['issued', 'fulfilled', 'part_paid']), 'route' => route('documents.index', ['module' => 'customer-invoices', 'status' => 'issued'])],
                ],
                'incoming' => [
                    ['label' => 'Purchase requests', 'count' => $documentCount('purchase_request'), 'route' => route('documents.index', 'purchase-requests')],
                    ['label' => 'Purchase Orders', 'count' => $documentCount('supplier_po'), 'route' => route('documents.index', 'supplier-pos')],
                    ['label' => 'Receipts', 'count' => $documentCount('goods_receipt'), 'route' => route('documents.index', 'goods-receipts')],
                    ['label' => 'Supplier invoices', 'count' => $documentCount('supplier_invoice'), 'route' => route('documents.index', 'supplier-invoices')],
                ],
            ],
            'recentDocuments' => Document::with(['customer', 'supplier'])
                ->latest()
                ->limit(10)
                ->get(),
            'pendingApprovals' => Approval::with(['document.customer', 'document.supplier', 'requester'])
                ->where('status', 'pending')
                ->latest()
                ->limit(10)
                ->get(),
            'cashMovement' => [
                'incoming' => Payment::where('direction', 'incoming')->where('payment_date', '>=', now()->subDays(30))->sum('amount'),
                'outgoing' => Payment::where('direction', 'outgoing')->where('payment_date', '>=', now()->subDays(30))->sum('amount'),
            ],
            'approvalStats' => [
                'pending' => Approval::where('status', 'pending')->count(),
                'rejected' => Approval::where('status', 'rejected')->count(),
                'approved' => Approval::where('status', 'approved')->count(),
            ],
            'invoiceAging' => [
                ['label' => 'Not due', 'value' => $openInvoiceQuery()->where(fn ($query) => $query->whereNull('due_date')->orWhereDate('due_date', '>=', $today))->sum('total'), 'accent' => 'primary'],
                ['label' => '1 - 30 days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', now()->subDays(30)->toDateString())->sum('total'), 'accent' => 'blue'],
                ['label' => '31 - 60 days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', now()->subDays(30)->toDateString())->whereDate('due_date', '>=', now()->subDays(60)->toDateString())->sum('total'), 'accent' => 'amber'],
                ['label' => '61+ days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', now()->subDays(60)->toDateString())->sum('total'), 'accent' => 'red'],
            ],
            'quickActions' => [
                ['label' => 'Create quotation', 'icon' => 'quote', 'route' => route('documents.create', 'customer-quotations')],
                ['label' => 'Create customer invoice', 'icon' => 'receipt', 'route' => route('documents.create', 'customer-invoices')],
                ['label' => 'Create purchase request', 'icon' => 'purchase', 'route' => route('documents.create', 'purchase-requests')],
                ['label' => 'Create purchase order', 'icon' => 'purchase', 'route' => route('documents.create', 'supplier-pos')],
                ['label' => 'Record goods receipt', 'icon' => 'receipt', 'route' => route('documents.create', 'goods-receipts')],
                ['label' => 'Open payments', 'icon' => 'payments', 'route' => route('payments.index')],
            ],
            'topCustomers' => Document::with('customer')
                ->selectRaw('customer_id, SUM(total) as total')
                ->where('type', 'customer_invoice')
                ->whereNotNull('customer_id')
                ->groupBy('customer_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ]);
    }
}

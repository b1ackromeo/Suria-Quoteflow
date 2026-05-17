<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
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
        $openReceivables = max($receivableTotal - $receivablePaid, 0);
        $openPayables = max($payableTotal - $payablePaid, 0);

        $documentCount = fn (string $type, array $statuses = []) => Document::where('type', $type)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->count();

        $openInvoiceQuery = fn () => Document::whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->whereNotIn('status', ['paid', 'closed', 'cancelled']);

        $today = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();
        $pendingApprovalCount = Approval::where('status', 'pending')->count();
        $supplierInvoiceVerificationCount = Document::where('type', 'supplier_invoice')
            ->whereIn('status', ['draft', 'rejected'])
            ->whereDoesntHave('attachmentExtractions', fn ($query) => $query->where('status', 'verified'))
            ->count();
        $supplierInvoiceMatchingCount = Document::where('type', 'supplier_invoice')
            ->whereIn('status', ['approved', 'issued', 'received'])
            ->count();
        $overdueReceivablesQuery = fn () => Document::where('type', 'customer_invoice')
            ->whereIn('status', ['issued', 'part_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);
        $overdueReceivablesCount = $overdueReceivablesQuery()->count();
        $overdueReceivablesTotal = $overdueReceivablesQuery()->sum('total');
        $supplierPaymentsDueQuery = fn () => Document::where('type', 'supplier_invoice')
            ->whereIn('status', ['matched', 'part_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $nextWeek);
        $supplierPaymentsDueCount = $supplierPaymentsDueQuery()->count();
        $supplierPaymentsDueTotal = $supplierPaymentsDueQuery()->sum('total');
        $invoiceAging = [
            ['label' => 'Not due', 'value' => $openInvoiceQuery()->where(fn ($query) => $query->whereNull('due_date')->orWhereDate('due_date', '>=', $today))->sum('total'), 'accent' => 'primary'],
            ['label' => '1 - 30 days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', now()->subDays(30)->toDateString())->sum('total'), 'accent' => 'blue'],
            ['label' => '31 - 60 days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', now()->subDays(30)->toDateString())->whereDate('due_date', '>=', now()->subDays(60)->toDateString())->sum('total'), 'accent' => 'amber'],
            ['label' => '61+ days', 'value' => $openInvoiceQuery()->whereDate('due_date', '<', now()->subDays(60)->toDateString())->sum('total'), 'accent' => 'red'],
        ];
        $agingTotal = collect($invoiceAging)->sum('value');
        $invoiceAging = collect($invoiceAging)
            ->map(fn (array $bucket) => $bucket + [
                'percent' => $agingTotal > 0 ? max(4, (int) round(((float) $bucket['value'] / (float) $agingTotal) * 100)) : 0,
            ])
            ->all();
        $largestExposure = max($openReceivables, $openPayables, $agingTotal, 1);
        $financialExposure = [
            ['label' => 'Open receivables', 'value' => $openReceivables, 'note' => 'Customer invoices awaiting collection', 'route' => route('reports.index'), 'icon' => 'payments', 'tone' => 'receivable'],
            ['label' => 'Open payables', 'value' => $openPayables, 'note' => 'Supplier invoices awaiting payment', 'route' => route('reports.index'), 'icon' => 'purchase', 'tone' => 'payable'],
            ['label' => 'Due aging', 'value' => $agingTotal, 'note' => 'Open invoices grouped by due date', 'route' => route('reports.index'), 'icon' => 'reports', 'tone' => 'aging'],
        ];
        $financialExposure = collect($financialExposure)
            ->map(fn (array $item) => $item + [
                'percent' => $item['value'] > 0 ? max(8, (int) round(((float) $item['value'] / (float) $largestExposure) * 100)) : 0,
            ])
            ->all();

        $todayWork = [
            [
                'label' => 'Pending approvals',
                'count' => $pendingApprovalCount,
                'note' => 'Manager action waiting now',
                'route' => route('approvals.pending'),
                'action' => 'Review approvals',
                'tone' => 'waiting',
            ],
            [
                'label' => 'Verify supplier invoices',
                'count' => $supplierInvoiceVerificationCount,
                'note' => 'Details must be checked before approval',
                'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'draft']),
                'action' => 'Open supplier invoices',
                'tone' => 'warning',
            ],
            [
                'label' => 'Match supplier invoices',
                'count' => $supplierInvoiceMatchingCount,
                'note' => 'Approved invoices waiting for matching',
                'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'approved']),
                'action' => 'Review matching',
                'tone' => 'ready',
            ],
            [
                'label' => 'Overdue receivables',
                'count' => $overdueReceivablesCount,
                'note' => 'RM '.number_format($overdueReceivablesTotal, 2).' overdue',
                'route' => route('documents.index', 'customer-invoices'),
                'action' => 'Review invoices',
                'tone' => 'danger',
            ],
            [
                'label' => 'Supplier payments due',
                'count' => $supplierPaymentsDueCount,
                'note' => 'RM '.number_format($supplierPaymentsDueTotal, 2).' due within 7 days',
                'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'matched']),
                'action' => 'Review payments',
                'tone' => 'money',
            ],
        ];
        $todayWorkItemCount = collect($todayWork)->sum('count');
        $todayWorkTotal = max($todayWorkItemCount, 1);
        $todayWork = collect($todayWork)
            ->map(fn (array $item) => $item + [
                'percent' => $item['count'] > 0 ? max(8, (int) round(((float) $item['count'] / (float) $todayWorkTotal) * 100)) : 0,
            ])
            ->all();

        $workflow = [
            'sales' => [
                ['label' => 'Quotations', 'count' => $documentCount('customer_quotation'), 'route' => route('documents.index', 'customer-quotations')],
                ['label' => 'Customer POs', 'count' => $documentCount('customer_po'), 'route' => route('documents.index', 'customer-pos')],
                ['label' => 'Customer invoices', 'count' => $documentCount('customer_invoice'), 'route' => route('documents.index', 'customer-invoices')],
                ['label' => 'Unpaid invoices', 'count' => $documentCount('customer_invoice', ['issued', 'fulfilled', 'part_paid']), 'route' => route('documents.index', ['module' => 'customer-invoices', 'status' => 'issued'])],
            ],
            'purchasing' => [
                ['label' => 'Purchase requests', 'count' => $documentCount('purchase_request'), 'route' => route('documents.index', 'purchase-requests')],
                ['label' => 'Purchase orders', 'count' => $documentCount('supplier_po'), 'route' => route('documents.index', 'supplier-pos')],
                ['label' => 'Receipts', 'count' => $documentCount('goods_receipt'), 'route' => route('documents.index', 'goods-receipts')],
                ['label' => 'Supplier invoices', 'count' => $documentCount('supplier_invoice'), 'route' => route('documents.index', 'supplier-invoices')],
            ],
        ];
        $workflow = collect($workflow)
            ->map(function (array $steps) {
                $largestStep = max(collect($steps)->max('count'), 1);

                return collect($steps)
                    ->map(fn (array $step) => $step + [
                        'percent' => $step['count'] > 0 ? max(12, (int) round(((float) $step['count'] / (float) $largestStep) * 100)) : 0,
                    ])
                    ->all();
            })
            ->all();
        $monthlyMovement = $this->monthlyMovement();

        return view('dashboard.index', [
            'todayWork' => $todayWork,
            'todayWorkItemCount' => $todayWorkItemCount,
            'financialExposure' => $financialExposure,
            'workflow' => $workflow,
            'invoiceAging' => $invoiceAging,
            'monthlyMovement' => $monthlyMovement,
            'quickActions' => [
                ['label' => 'Create quotation', 'icon' => 'quote', 'route' => route('documents.create', 'customer-quotations')],
                ['label' => 'Create customer invoice', 'icon' => 'receipt', 'route' => route('documents.create', 'customer-invoices')],
                ['label' => 'Create purchase request', 'icon' => 'purchase', 'route' => route('documents.create', 'purchase-requests')],
                ['label' => 'Create purchase order', 'icon' => 'purchase', 'route' => route('documents.create', 'supplier-pos')],
                ['label' => 'Record goods receipt', 'icon' => 'receipt', 'route' => route('documents.create', 'goods-receipts')],
            ],
        ]);
    }

    private function monthlyMovement(): array
    {
        $trendStart = now()->copy()->subMonths(5)->startOfMonth();
        $trendEnd = now()->copy()->addMonth()->startOfMonth();
        $driver = DB::connection()->getDriverName();
        $documentMonthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', issue_date)"
            : "DATE_FORMAT(issue_date, '%Y-%m')";
        $paymentMonthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', payment_date)"
            : "DATE_FORMAT(payment_date, '%Y-%m')";

        $months = collect(range(0, 5))
            ->mapWithKeys(function (int $offset) use ($trendStart) {
                $month = $trendStart->copy()->addMonths($offset);

                return [
                    $month->format('Y-m') => [
                        'key' => $month->format('Y-m'),
                        'label' => $month->format('M'),
                        'customer_invoices' => 0.0,
                        'supplier_invoices' => 0.0,
                        'incoming_payments' => 0.0,
                        'outgoing_payments' => 0.0,
                    ],
                ];
            });

        Document::selectRaw($documentMonthExpression.' as month, type, COALESCE(SUM(total), 0) as total')
            ->whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->where('issue_date', '>=', $trendStart->toDateString())
            ->where('issue_date', '<', $trendEnd->toDateString())
            ->groupBy('month', 'type')
            ->get()
            ->each(function ($row) use (&$months) {
                $field = $row->type === 'customer_invoice' ? 'customer_invoices' : 'supplier_invoices';
                $month = $months->get($row->month);

                if ($month) {
                    $month[$field] = (float) $row->total;
                    $months->put($row->month, $month);
                }
            });

        Payment::selectRaw($paymentMonthExpression.' as month, direction, COALESCE(SUM(amount), 0) as total')
            ->where('payment_date', '>=', $trendStart->toDateString())
            ->where('payment_date', '<', $trendEnd->toDateString())
            ->groupBy('month', 'direction')
            ->get()
            ->each(function ($row) use (&$months) {
                $field = $row->direction === 'incoming' ? 'incoming_payments' : 'outgoing_payments';
                $month = $months->get($row->month);

                if ($month) {
                    $month[$field] = (float) $row->total;
                    $months->put($row->month, $month);
                }
            });

        $largestValue = max($months->flatMap(fn (array $month) => [
            $month['customer_invoices'],
            $month['supplier_invoices'],
            $month['incoming_payments'],
            $month['outgoing_payments'],
        ])->max(), 1);

        return $months
            ->map(fn (array $month) => $month + [
                'customer_invoices_percent' => $month['customer_invoices'] > 0 ? max(8, (int) round(($month['customer_invoices'] / $largestValue) * 100)) : 0,
                'supplier_invoices_percent' => $month['supplier_invoices'] > 0 ? max(8, (int) round(($month['supplier_invoices'] / $largestValue) * 100)) : 0,
                'incoming_payments_percent' => $month['incoming_payments'] > 0 ? max(8, (int) round(($month['incoming_payments'] / $largestValue) * 100)) : 0,
                'outgoing_payments_percent' => $month['outgoing_payments'] > 0 ? max(8, (int) round(($month['outgoing_payments'] / $largestValue) * 100)) : 0,
            ])
            ->values()
            ->all();
    }
}

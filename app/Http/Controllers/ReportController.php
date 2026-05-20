<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $rangeDays = (int) $request->query('range', 30);
        $rangeDays = in_array($rangeDays, [30, 60, 90, 365], true) ? $rangeDays : 30;
        $periodStart = now()->subDays($rangeDays)->startOfDay();
        $previousPeriodStart = now()->subDays($rangeDays * 2)->startOfDay();
        $today = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();
        $receivableStatuses = ['issued', 'part_paid'];
        $payableStatuses = ['matched', 'part_paid'];

        $receivables = $this->openInvoiceList('customer_invoice', $receivableStatuses, 'customer');
        $payables = $this->openInvoiceList('supplier_invoice', $payableStatuses, 'supplier');
        $receivableCount = $this->openBalanceInvoiceBaseQuery('customer_invoice', $receivableStatuses)->count('documents.id');
        $payableCount = $this->openBalanceInvoiceBaseQuery('supplier_invoice', $payableStatuses)->count('documents.id');
        $receivableBalance = $this->balanceTotal('customer_invoice', $receivableStatuses);
        $payableBalance = $this->balanceTotal('supplier_invoice', $payableStatuses);
        $overdueReceivableBalance = $this->balanceTotal(
            'customer_invoice',
            $receivableStatuses,
            fn (Builder $query) => $query
                ->whereNotNull('documents.due_date')
                ->whereDate('documents.due_date', '<', $today)
        );
        $supplierDueSoonBalance = $this->balanceTotal(
            'supplier_invoice',
            $payableStatuses,
            fn (Builder $query) => $query
                ->whereNotNull('documents.due_date')
                ->whereDate('documents.due_date', '<=', $nextWeek)
        );

        $incomingPayments = $this->paymentTotal('incoming', $periodStart);
        $outgoingPayments = $this->paymentTotal('outgoing', $periodStart);
        $previousIncomingPayments = $this->paymentTotal('incoming', $previousPeriodStart, $periodStart);
        $previousOutgoingPayments = $this->paymentTotal('outgoing', $previousPeriodStart, $periodStart);
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', issue_date)"
            : "DATE_FORMAT(issue_date, '%Y-%m')";

        $monthlyInvoices = Document::selectRaw($monthExpression.' as month, type, SUM(total) as total')
            ->whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->where('issue_date', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();

        $netExposure = $receivableBalance - $payableBalance;
        $largestExposure = max($receivableBalance, $payableBalance, abs($netExposure), 1);
        $cashPeak = max($incomingPayments, $outgoingPayments, $previousIncomingPayments, $previousOutgoingPayments, abs($incomingPayments - $outgoingPayments), 1);

        return view('reports.index', [
            'receivables' => $receivables,
            'payables' => $payables,
            'monthlyInvoices' => $monthlyInvoices,
            'monthlyInvoiceChart' => $this->monthlyInvoiceChart($monthlyInvoices),
            'rangeDays' => $rangeDays,
            'receivableCount' => $receivableCount,
            'payableCount' => $payableCount,
            'incomingPayments' => $incomingPayments,
            'outgoingPayments' => $outgoingPayments,
            'previousIncomingPayments' => $previousIncomingPayments,
            'previousOutgoingPayments' => $previousOutgoingPayments,
            'receivableBalance' => $receivableBalance,
            'payableBalance' => $payableBalance,
            'netExposure' => $netExposure,
            'overdueReceivableBalance' => $overdueReceivableBalance,
            'supplierDueSoonBalance' => $supplierDueSoonBalance,
            'receivableAging' => $this->agingBuckets('customer_invoice', $receivableStatuses),
            'payableAging' => $this->agingBuckets('supplier_invoice', $payableStatuses),
            'exposureBars' => [
                [
                    'label' => 'Open receivables',
                    'helper' => $receivableCount.' customer invoices awaiting collection',
                    'amount' => $receivableBalance,
                    'percent' => $this->chartPercent($receivableBalance, $largestExposure),
                    'tone' => 'receivable',
                    'route' => route('documents.index', 'customer-invoices'),
                ],
                [
                    'label' => 'Open payables',
                    'helper' => $payableCount.' supplier invoices awaiting payment',
                    'amount' => $payableBalance,
                    'percent' => $this->chartPercent($payableBalance, $largestExposure),
                    'tone' => 'payable',
                    'route' => route('documents.index', 'supplier-invoices'),
                ],
            ],
            'cashMovementBars' => [
                [
                    'label' => 'Customer payments received',
                    'amount' => $incomingPayments,
                    'previous' => $previousIncomingPayments,
                    'percent' => $this->chartPercent($incomingPayments, $cashPeak),
                    'tone' => 'collected',
                ],
                [
                    'label' => 'Supplier payments made',
                    'amount' => $outgoingPayments,
                    'previous' => $previousOutgoingPayments,
                    'percent' => $this->chartPercent($outgoingPayments, $cashPeak),
                    'tone' => 'paid',
                ],
            ],
            'attentionCards' => [
                [
                    'label' => 'Overdue receivables',
                    'amount' => $overdueReceivableBalance,
                    'helper' => 'Customer balances past due date',
                    'route' => route('documents.index', 'customer-invoices'),
                    'tone' => 'danger',
                ],
                [
                    'label' => 'Supplier payments due soon',
                    'amount' => $supplierDueSoonBalance,
                    'helper' => 'Matched supplier invoices due within 7 days',
                    'route' => route('documents.index', 'supplier-invoices'),
                    'tone' => 'warning',
                ],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $report = $request->validate([
            'report' => ['required', 'in:receivables,payables,payments'],
        ])['report'];

        $filename = $report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            if ($report === 'payments') {
                fputcsv($handle, ['Date', 'Payment Type', 'Document', 'Currency', 'Amount', 'Method', 'Reference']);
                Payment::with('document')->orderBy('payment_date')->chunk(200, function ($payments) use ($handle) {
                    foreach ($payments as $payment) {
                        fputcsv($handle, [
                            optional($payment->payment_date)->format('Y-m-d'),
                            $payment->typeDisplay(),
                            $payment->document?->document_number,
                            $payment->document?->currency,
                            $payment->amount,
                            $payment->method,
                            $payment->reference,
                        ]);
                    }
                });
                fclose($handle);

                return;
            }

            $type = $report === 'receivables' ? 'customer_invoice' : 'supplier_invoice';
            fputcsv($handle, ['Document', 'Party', 'Issue Date', 'Due Date', 'Status', 'Currency', 'Total', 'Paid', 'Balance']);
            Document::with(['customer', 'supplier'])
                ->withSum('payments as paid_total', 'amount')
                ->where('type', $type)
                ->orderBy('issue_date')
                ->chunk(200, function ($documents) use ($handle) {
                    foreach ($documents as $document) {
                        $paidTotal = (float) ($document->paid_total ?? 0);

                        fputcsv($handle, [
                            $document->document_number,
                            $document->partyName(),
                            optional($document->issue_date)->format('Y-m-d'),
                            optional($document->due_date)->format('Y-m-d'),
                            $document->status,
                            $document->currency,
                            $document->total,
                            $paidTotal,
                            max(0, (float) $document->total - $paidTotal),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function openBalanceInvoiceBaseQuery(string $type, array $statuses): Builder
    {
        $paymentTotals = $this->paymentTotalsSubquery();

        return Document::query()
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.document_id', '=', 'documents.id')
            ->where('documents.type', $type)
            ->whereIn('documents.status', $statuses)
            ->whereRaw('documents.total - COALESCE(payment_totals.paid_total, 0) > 0');
    }

    private function openInvoiceList(string $type, array $statuses, string $partyRelation)
    {
        return $this->openBalanceInvoiceBaseQuery($type, $statuses)
            ->select('documents.*')
            ->selectRaw('COALESCE(payment_totals.paid_total, 0) as paid_total')
            ->with($partyRelation)
            ->orderByRaw('CASE WHEN documents.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('documents.due_date')
            ->limit(8)
            ->get();
    }

    private function paymentTotal(string $direction, $startDate, $endDate = null): float
    {
        return (float) Payment::where('direction', $direction)
            ->where('payment_date', '>=', $startDate)
            ->when($endDate, fn ($query) => $query->where('payment_date', '<', $endDate))
            ->sum('amount');
    }

    private function balanceTotal(string $type, array $statuses, ?\Closure $scope = null): float
    {
        $query = $this->openBalanceInvoiceBaseQuery($type, $statuses);

        if ($scope) {
            $scope($query);
        }

        return (float) $query
            ->selectRaw('COALESCE(SUM(documents.total - COALESCE(payment_totals.paid_total, 0)), 0) as balance_total')
            ->value('balance_total');
    }

    private function paymentTotalsSubquery(): Builder
    {
        return Payment::query()
            ->select('document_id', DB::raw('SUM(amount) as paid_total'))
            ->groupBy('document_id');
    }

    private function agingBuckets(string $type, array $statuses): array
    {
        $today = now()->toDateString();
        $thirtyDaysAgo = now()->subDays(30)->toDateString();
        $sixtyDaysAgo = now()->subDays(60)->toDateString();

        return [
            [
                'label' => 'Not due',
                'helper' => 'Due today or later',
                'amount' => $this->balanceTotal(
                    $type,
                    $statuses,
                    fn (Builder $query) => $query
                        ->where(function ($query) use ($today) {
                            $query->whereNull('documents.due_date')->orWhereDate('documents.due_date', '>=', $today);
                        })
                ),
            ],
            [
                'label' => '1-30 days',
                'helper' => 'Recently overdue',
                'amount' => $this->balanceTotal(
                    $type,
                    $statuses,
                    fn (Builder $query) => $query
                        ->whereDate('documents.due_date', '<', $today)
                        ->whereDate('documents.due_date', '>=', $thirtyDaysAgo)
                ),
            ],
            [
                'label' => '31-60 days',
                'helper' => 'Needs follow-up',
                'amount' => $this->balanceTotal(
                    $type,
                    $statuses,
                    fn (Builder $query) => $query
                        ->whereDate('documents.due_date', '<', $thirtyDaysAgo)
                        ->whereDate('documents.due_date', '>=', $sixtyDaysAgo)
                ),
            ],
            [
                'label' => '60+ days',
                'helper' => 'Escalation risk',
                'amount' => $this->balanceTotal(
                    $type,
                    $statuses,
                    fn (Builder $query) => $query->whereDate('documents.due_date', '<', $sixtyDaysAgo)
                ),
            ],
        ];
    }

    private function monthlyInvoiceChart($monthlyInvoices): array
    {
        $rowsByMonth = $monthlyInvoices->groupBy('month');
        $months = collect(range(11, 0))->map(function (int $offset) use ($rowsByMonth) {
            $date = now()->subMonths($offset)->startOfMonth();
            $key = $date->format('Y-m');
            $rows = $rowsByMonth->get($key, collect());
            $customerTotal = (float) $rows
                ->where('type', 'customer_invoice')
                ->sum('total');
            $supplierTotal = (float) $rows
                ->where('type', 'supplier_invoice')
                ->sum('total');

            return [
                'month' => $key,
                'label' => $date->format('M'),
                'customer_total' => $customerTotal,
                'supplier_total' => $supplierTotal,
            ];
        });

        $largest = max(
            $months->max('customer_total') ?? 0,
            $months->max('supplier_total') ?? 0,
            1
        );

        return $months
            ->map(fn (array $month) => array_merge($month, [
                'customer_percent' => $this->chartPercent((float) $month['customer_total'], $largest),
                'supplier_percent' => $this->chartPercent((float) $month['supplier_total'], $largest),
            ]))
            ->values()
            ->all();
    }

    private function chartPercent(float $value, float $largest): int
    {
        if ($value <= 0 || $largest <= 0) {
            return 0;
        }

        return (int) min(100, max(4, round(($value / $largest) * 100));
    }
}

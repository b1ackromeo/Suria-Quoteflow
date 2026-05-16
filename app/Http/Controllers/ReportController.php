<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $receivables = Document::with(['customer', 'payments'])
            ->where('type', 'customer_invoice')
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->latest('issue_date')
            ->limit(20)
            ->get();

        $payables = Document::with(['supplier', 'payments'])
            ->where('type', 'supplier_invoice')
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->latest('issue_date')
            ->limit(20)
            ->get();

        $monthlyInvoices = Document::selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as month, direction, SUM(total) as total')
            ->whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->where('issue_date', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month', 'direction')
            ->orderBy('month')
            ->get();

        return view('reports.index', [
            'receivables' => $receivables,
            'payables' => $payables,
            'monthlyInvoices' => $monthlyInvoices,
            'incomingPayments' => Payment::where('direction', 'incoming')->where('payment_date', '>=', now()->subDays(30))->sum('amount'),
            'outgoingPayments' => Payment::where('direction', 'outgoing')->where('payment_date', '>=', now()->subDays(30))->sum('amount'),
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
                fputcsv($handle, ['Date', 'Direction', 'Document', 'Amount', 'Method', 'Reference']);
                Payment::with('document')->orderBy('payment_date')->chunk(200, function ($payments) use ($handle) {
                    foreach ($payments as $payment) {
                        fputcsv($handle, [
                            optional($payment->payment_date)->format('Y-m-d'),
                            $payment->direction,
                            $payment->document?->document_number,
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
            fputcsv($handle, ['Document', 'Party', 'Issue Date', 'Due Date', 'Status', 'Total', 'Paid', 'Balance']);
            Document::with(['customer', 'supplier', 'payments'])
                ->where('type', $type)
                ->orderBy('issue_date')
                ->chunk(200, function ($documents) use ($handle) {
                    foreach ($documents as $document) {
                        fputcsv($handle, [
                            $document->document_number,
                            $document->partyName(),
                            optional($document->issue_date)->format('Y-m-d'),
                            optional($document->due_date)->format('Y-m-d'),
                            $document->status,
                            $document->total,
                            $document->payments->sum('amount'),
                            $document->balanceDue(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

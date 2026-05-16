<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Payment;
use App\Services\Documents\PaymentEligibilityService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private PaymentEligibilityService $paymentEligibility)
    {
    }

    public function index(): View
    {
        return view('payments.index', [
            'payments' => Payment::with(['document.customer', 'document.supplier', 'creator'])
                ->latest('payment_date')
                ->paginate(25),
        ]);
    }

    public function create(Document $document): View
    {
        $this->ensurePaymentAccess();
        $this->ensurePaymentEligible($document);

        return view('payments.form', [
            'document' => $document->load(['customer', 'supplier', 'payments']),
            'payment' => new Payment([
                'payment_date' => now()->toDateString(),
                'direction' => $document->direction === 'outgoing' ? 'incoming' : 'outgoing',
            ]),
        ]);
    }

    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->ensurePaymentAccess();
        $this->ensurePaymentEligible($document);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'method' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $document->payments()->create($data + [
            'direction' => $document->direction === 'outgoing' ? 'incoming' : 'outgoing',
            'created_by' => auth()->id(),
        ]);

        $paid = $document->payments()->sum('amount');
        $before = $document->only(['status']);

        if ($paid >= (float) $document->total) {
            $document->update(['status' => 'paid']);
        } elseif ($paid > 0) {
            $document->update(['status' => 'part_paid']);
        }

        Audit::record('payment_recorded', $payment, null, $payment->toArray());
        Audit::record('document_payment_status_updated', $document, $before, $document->only(['status']));

        return redirect()->route('documents.show', $document)->with('status', 'Payment recorded.');
    }

    private function ensurePaymentAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager', 'accounts'), 403);
    }

    private function ensurePaymentEligible(Document $document): void
    {
        if ($this->paymentEligibility->canRecordPayment($document)) {
            return;
        }

        throw ValidationException::withMessages([
            'payment' => $this->paymentEligibility->blockedReason($document),
        ]);
    }
}

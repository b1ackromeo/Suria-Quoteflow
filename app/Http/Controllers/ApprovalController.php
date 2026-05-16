<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function indexPending(): View
    {
        return view('approvals.pending', [
            'approvals' => Approval::with(['document.customer', 'document.supplier', 'requester'])
                ->where('status', 'pending')
                ->latest()
                ->paginate(25),
        ]);
    }
}

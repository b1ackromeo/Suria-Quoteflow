<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\View\View;

class AuditTrailController extends Controller
{
    public function index(): View
    {
        return view('audit.index', [
            'audits' => AuditTrail::with('user')->latest()->paginate(30),
        ]);
    }
}

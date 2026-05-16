<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;
        $category = trim($request->string('category')->toString());

        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->when($search, fn ($query, $q) => SearchFilters::suppliers($query, $q))
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'searchTerm' => $search,
            'statusFilter' => $status,
            'categoryFilter' => $category,
            'categoryOptions' => Supplier::query()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->merge(Supplier::CATEGORY_SUGGESTIONS)
                ->unique()
                ->values(),
            'summary' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('is_active', true)->count(),
                'inactive' => Supplier::where('is_active', false)->count(),
                'average_terms' => (int) round(Supplier::avg('payment_terms_days') ?? 0),
            ],
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', [
            'supplier' => new Supplier(['is_active' => true, 'payment_terms_days' => 30]),
            'categoryOptions' => Supplier::CATEGORY_SUGGESTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));
        Audit::record('supplier_created', $supplier, null, $supplier->toArray());

        return redirect()->route('suppliers.index')->with('status', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', [
            'supplier' => $supplier,
            'categoryOptions' => collect([$supplier->category])
                ->filter()
                ->merge(Supplier::CATEGORY_SUGGESTIONS)
                ->unique()
                ->values(),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $before = $supplier->toArray();
        $supplier->update($this->validated($request, $supplier->id));
        Audit::record('supplier_updated', $supplier, $before, $supplier->fresh()->toArray());

        return redirect()->route('suppliers.index')->with('status', 'Supplier updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80', 'unique:suppliers,code,'.($ignoreId ?? 'NULL').',id'],
            'category' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:120'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}

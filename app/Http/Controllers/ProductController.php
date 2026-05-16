<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $type = $request->string('type')->toString();
        $type = in_array($type, ['product', 'service'], true) ? $type : null;

        return view('products.index', [
            'products' => Product::query()
                ->when($search, fn ($query, $q) => SearchFilters::products($query, $q))
                ->when($type, fn ($query, $value) => $query->where('type', $value))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'searchTerm' => $search,
            'typeFilter' => $type,
            'summary' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'products' => Product::where('type', 'product')->count(),
                'services' => Product::where('type', 'service')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('products.form', ['product' => new Product(['type' => 'product', 'unit' => 'unit', 'tax_rate' => 0, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::create($this->validated($request));
        Audit::record('product_created', $product, null, $product->toArray());

        return redirect()->route('products.index')->with('status', 'Item created.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $before = $product->toArray();
        $product->update($this->validated($request, $product->id));
        Audit::record('product_updated', $product, $before, $product->fresh()->toArray());

        return redirect()->route('products.index')->with('status', 'Item updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:product,service'],
            'sku' => ['nullable', 'string', 'max:120', 'unique:products,sku,'.($ignoreId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:40'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

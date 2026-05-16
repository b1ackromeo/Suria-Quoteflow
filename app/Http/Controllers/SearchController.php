<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\SearchFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $term = trim($request->string('q')->toString());

        return view('search.index', [
            'q' => $term,
            'documents' => $term === ''
                ? collect()
                : SearchFilters::documents(Document::with(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments', 'attachments']), $term)
                    ->latest('issue_date')
                    ->latest('id')
                    ->limit(25)
                    ->get(),
            'customers' => $term === ''
                ? collect()
                : SearchFilters::customers(Customer::query(), $term)
                    ->orderBy('name')
                    ->limit(10)
                    ->get(),
            'suppliers' => $term === ''
                ? collect()
                : SearchFilters::suppliers(Supplier::query(), $term)
                    ->orderBy('name')
                    ->limit(10)
                    ->get(),
            'products' => $term === ''
                ? collect()
                : SearchFilters::products(Product::query(), $term)
                    ->orderBy('name')
                    ->limit(10)
                    ->get(),
        ]);
    }
}

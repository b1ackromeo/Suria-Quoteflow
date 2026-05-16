<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        return view('customers.index', [
            'customers' => Customer::query()
                ->when($search, fn ($query, $q) => SearchFilters::customers($query, $q))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'searchTerm' => $search,
            'summary' => [
                'total' => Customer::count(),
                'active' => Customer::where('is_active', true)->count(),
                'average_terms' => (int) round(Customer::avg('payment_terms_days') ?? 0),
            ],
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer(['is_active' => true, 'payment_terms_days' => 30])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));
        Audit::record('customer_created', $customer, null, $customer->toArray());

        return redirect()->route('customers.index')->with('status', 'Customer created.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $before = $customer->toArray();
        $customer->update($this->validated($request, $customer->id));
        Audit::record('customer_updated', $customer, $before, $customer->fresh()->toArray());

        return redirect()->route('customers.index')->with('status', 'Customer updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80', 'unique:customers,code,'.($ignoreId ?? 'NULL').',id'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:120'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}

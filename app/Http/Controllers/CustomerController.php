<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(Request $request): Response|JsonResponse
    {
        $search = $this->customerService->normalizeSearch($request->query('search'));
        $customers = $this->customerService->paginate($search);

        if ($request->expectsJson()) {
            return response()->json($customers);
        }

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json($customer, 201);
        }

        return to_route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(int $customer): JsonResponse
    {
        return response()->json($this->customerService->find($customer));
    }

    public function edit(int $customer): Response
    {
        return Inertia::render('customers/edit', ['customer' => $this->customerService->find($customer)]);
    }

    public function update(UpdateCustomerRequest $request, int $customer): RedirectResponse|JsonResponse
    {
        $updatedCustomer = $this->customerService->update($this->customerService->find($customer), $request->validated());

        if ($request->expectsJson()) {
            return response()->json($updatedCustomer);
        }

        return to_route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, int $customer): RedirectResponse|JsonResponse
    {
        $this->customerService->delete($this->customerService->find($customer));

        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        return to_route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}

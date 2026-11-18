<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->customerService->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers')->where(fn ($query) => $query->where('name', $request->input('name'))),
            ],
        ]);

        return response()->json($this->customerService->create($data), 201);
    }

    public function show(int $customer): JsonResponse
    {
        return response()->json($this->customerService->find($customer));
    }

    public function update(Request $request, int $customer): JsonResponse
    {
        $model = Customer::query()->findOrFail($customer);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers')
                    ->where(fn ($query) => $query->where('name', $request->input('name')))
                    ->ignore($model->id),
            ],
        ]);

        return response()->json($this->customerService->update($model, $data));
    }

    public function destroy(int $customer): JsonResponse
    {
        $model = Customer::query()->findOrFail($customer);
        $this->customerService->delete($model);

        return response()->json(status: 204);
    }
}

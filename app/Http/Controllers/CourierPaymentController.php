<?php

namespace App\Http\Controllers;

use App\Services\CourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourierPaymentController extends Controller
{
    public function __construct(private readonly CourierService $courierService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->courierService->paginatePayments());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_status' => ['required', 'string', Rule::in(['pending', 'paid'])],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'paid_at' => ['nullable', 'date'],
        ]);

        return response()->json($this->courierService->createPayment($data), 201);
    }

    public function show(int $courierPayment): JsonResponse
    {
        return response()->json($this->courierService->findPayment($courierPayment));
    }

    public function update(Request $request, int $courierPayment): JsonResponse
    {
        $data = $request->validate([
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_status' => ['required', 'string', Rule::in(['pending', 'paid'])],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $payment = $this->courierService->findPayment($courierPayment);

        return response()->json($this->courierService->updatePayment($payment, $data));
    }

    public function destroy(int $courierPayment): JsonResponse
    {
        $payment = $this->courierService->findPayment($courierPayment);
        $this->courierService->deletePayment($payment);

        return response()->json(status: 204);
    }
}

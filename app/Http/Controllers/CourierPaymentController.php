<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourierPaymentRequest;
use App\Http\Requests\UpdateCourierPaymentRequest;
use App\Services\CourierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierPaymentController extends Controller
{
    public function __construct(private readonly CourierPaymentService $paymentService) {}

    public function index(Request $request): Response|JsonResponse
    {
        $search = $this->paymentService->normalizeSearch($request->query('search'));
        $archived = $request->boolean('archived');
        $payments = $this->paymentService->paginate($search, $archived);

        if ($request->expectsJson()) {
            return response()->json($payments);
        }

        return Inertia::render('courier-payments/index', [
            'payments' => $payments,
            'search' => $search ?? '',
            'archived' => $archived,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('courier-payments/create', $this->paymentService->getFormOptions());
    }

    public function store(StoreCourierPaymentRequest $request): RedirectResponse|JsonResponse
    {
        $payment = $this->paymentService->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json($payment->load(['parcel.sender', 'parcel.recipient']), 201);
        }

        return to_route('courier-payments.index')->with('success', 'Payment created successfully.');
    }

    public function show(int $courierPayment): JsonResponse
    {
        return response()->json($this->paymentService->find($courierPayment));
    }

    public function edit(int $courierPayment): Response
    {
        return Inertia::render('courier-payments/edit', [
            ...$this->paymentService->getFormOptions(),
            'payment' => $this->paymentService->find($courierPayment),
        ]);
    }

    public function update(UpdateCourierPaymentRequest $request, int $courierPayment): RedirectResponse|JsonResponse
    {
        $payment = $this->paymentService->update($this->paymentService->find($courierPayment), $request->validated());

        if ($request->expectsJson()) {
            return response()->json($payment->load(['parcel.sender', 'parcel.recipient']));
        }

        return to_route('courier-payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(Request $request, int $courierPayment): RedirectResponse|JsonResponse
    {
        $this->paymentService->archive($this->paymentService->find($courierPayment));

        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        return to_route('courier-payments.index')->with('success', 'Payment archived successfully.');
    }

    public function restore(int $courierPayment): RedirectResponse
    {
        $this->paymentService->restore($courierPayment);

        return to_route('courier-payments.index')->with('success', 'Payment restored successfully.');
    }
}

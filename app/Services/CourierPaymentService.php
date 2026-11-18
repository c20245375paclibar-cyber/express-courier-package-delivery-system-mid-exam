<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourierPayment;
use App\Models\Parcel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CourierPaymentService
{
    public const STATUSES = [
        PaymentStatus::Pending->value,
        PaymentStatus::Paid->value,
        PaymentStatus::Failed->value,
        PaymentStatus::Refunded->value,
    ];

    public const METHODS = [
        PaymentMethod::Cash->value,
        PaymentMethod::Card->value,
        PaymentMethod::BankTransfer->value,
        PaymentMethod::MobileWallet->value,
    ];

    public function sanitizeInput(array $data): array
    {
        foreach (['payment_status', 'payment_method'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = mb_strtolower($this->cleanText($data[$field]), 'UTF-8');
            }
        }

        return $data;
    }

    public function normalizeSearch(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = $this->cleanText($search);

        return $search === '' ? null : $search;
    }

    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return CourierPayment::query()
            ->with('parcel:id,tracking_number')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('payment_status', 'like', "%{$search}%")
                ->orWhereHas('parcel', fn ($query) => $query->where('tracking_number', 'like', "%{$search}%")))
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function getFormOptions(): array
    {
        return [
            'parcels' => Parcel::query()->orderBy('tracking_number')->get(['id', 'tracking_number', 'shipping_cost']),
            'paymentStatuses' => self::STATUSES,
            'paymentMethods' => self::METHODS,
        ];
    }

    public function find(int $id): CourierPayment
    {
        return CourierPayment::query()
            ->with(['parcel.sender', 'parcel.recipient'])
            ->findOrFail($id);
    }

    public function create(array $data): CourierPayment
    {
        $data = $this->sanitizeInput($data);
        $this->assertPaymentOptions($data['payment_status'], $data['payment_method'] ?? null);
        $data['paid_at'] = $data['payment_status'] === PaymentStatus::Paid->value ? ($data['paid_at'] ?? now()) : null;

        return CourierPayment::query()->create($data);
    }

    public function update(CourierPayment $payment, array $data): CourierPayment
    {
        $data = $this->sanitizeInput($data);
        $this->assertPaymentOptions($data['payment_status'], $data['payment_method'] ?? null);
        $data['paid_at'] = $data['payment_status'] === PaymentStatus::Paid->value
            ? ($data['paid_at'] ?? $payment->paid_at ?? now())
            : null;
        $payment->update($data);

        return $payment->refresh();
    }

    public function delete(CourierPayment $payment): void
    {
        $payment->delete();
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    private function assertPaymentOptions(string $status, ?string $method): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['payment_status' => 'Choose a valid payment status.']);
        }
        if ($method !== null && $method !== '' && ! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['payment_method' => 'Choose a valid payment method.']);
        }
    }
}

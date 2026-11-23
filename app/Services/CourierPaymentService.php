<?php

namespace App\Services;

use App\Models\CourierPayment;
use App\Models\Parcel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentMethod
{
    public const Cash = 'cash';
    public const Card = 'card';
    public const BankTransfer = 'bank_transfer';
    public const MobileWallet = 'mobile_wallet';

    public static function cases(): array
    {
        return [
            ['value' => self::Cash],
            ['value' => self::Card],
            ['value' => self::BankTransfer],
            ['value' => self::MobileWallet],
        ];
    }
}

final class PaymentStatus
{
    public const Pending = 'pending';
    public const Paid = 'paid';
    public const Failed = 'failed';
    public const Refunded = 'refunded';

    public static function cases(): array
    {
        return [
            ['value' => self::Pending],
            ['value' => self::Paid],
            ['value' => self::Failed],
            ['value' => self::Refunded],
        ];
    }
}

class CourierPaymentService
{
    public const STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    public const METHODS = ['cash', 'card', 'bank_transfer', 'mobile_wallet'];

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

    public function paginate(?string $search = null, bool $archived = false): LengthAwarePaginator
    {
        return CourierPayment::query() // THIS IS CORRECT
            ->when($archived, fn ($query) => $query->onlyTrashed())
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
            'parcels' => Parcel::query()->orderBy('tracking_number')->get(['id', 'tracking_number', 'shipping_cost']), // THIS IS CORRECT
            'paymentStatuses' => self::STATUSES,
            'paymentMethods' => self::METHODS,
        ];
    }

    public function find(int $id): CourierPayment
    {
        return CourierPayment::query()
            ->select(['id', 'parcel_id', 'amount', 'payment_status', 'payment_method', 'paid_at'])
            ->with([
                'parcel:id,tracking_number,sender_customer_id,recipient_customer_id',
                'parcel.sender:id,name',
                'parcel.recipient:id,name',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): CourierPayment
    {
        $data = $this->sanitizeInput($data);
        $this->assertPaymentOptions($data['payment_status'], $data['payment_method'] ?? null);
        $data['paid_at'] = $data['payment_status'] === PaymentStatus::Paid ? ($data['paid_at'] ?? now()) : null;

        return DB::transaction(function () use ($data): CourierPayment {
            return CourierPayment::query()->create($data);
        });
    }

    public function update(CourierPayment $payment, array $data): CourierPayment
    {
        $data = $this->sanitizeInput($data);
        $this->assertPaymentOptions($data['payment_status'], $data['payment_method'] ?? null);
        $data['paid_at'] = $data['payment_status'] === PaymentStatus::Paid
            ? ($data['paid_at'] ?? $payment->paid_at ?? now())
            : null;
        $payment->update($data);

        return $payment->refresh();
    }

    public function archive(CourierPayment $payment): void
    {
        $payment->delete();
    }

    public function restore(int $id): void
    {
        CourierPayment::query()->withTrashed()->findOrFail($id)->restore();
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

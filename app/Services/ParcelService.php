<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\ShipmentStatus;
use App\Models\Parcel;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelService
{
    public const DELIVERY_TYPES = [
        DeliveryType::Standard->value,
        DeliveryType::Express->value,
        DeliveryType::Overnight->value,
    ];

    public const SHIPMENT_STATUSES = [
        ShipmentStatus::Received->value,
        ShipmentStatus::PickedUp->value,
        ShipmentStatus::InTransit->value,
        ShipmentStatus::OutForDelivery->value,
        ShipmentStatus::Delivered->value,
        ShipmentStatus::Exception->value,
    ];

    private const BASE_RATE_PER_KG = 5.00;

    private const SPEED_MULTIPLIERS = ['standard' => 1.0, 'express' => 1.5, 'overnight' => 2.5];

    public static function isPlausibleName(mixed $value): bool
    {
        return CustomerService::isPlausibleName($value);
    }

    public static function isPlausibleAddress(mixed $value): bool
    {
        return CustomerService::isPlausibleAddress($value);
    }

    public static function isPlausibleHubName(mixed $value): bool
    {
        return TransitHubService::isPlausibleName($value);
    }

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly TransitHubService $transitHubService,
    ) {}

    public function sanitizeInput(array $data): array
    {
        foreach (['tracking_number', 'sender_name', 'recipient_name', 'current_hub_name', 'initial_status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanText($data[$field]);
            }
        }

        foreach (['sender_address', 'recipient_address'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanAddress($data[$field]);
            }
            if (array_key_exists('current_hub_name', $data) && $data['current_hub_name'] === '') {
                $data['current_hub_name'] = null;
            }
        }

        if (array_key_exists('tracking_number', $data)) {
            $data['tracking_number'] = mb_strtoupper($data['tracking_number'], 'UTF-8');
        }
        if (array_key_exists('initial_status', $data)) {
            $data['initial_status'] = mb_strtolower($data['initial_status'], 'UTF-8');
        }
        if (array_key_exists('delivery_type', $data)) {
            $data['delivery_type'] = mb_strtolower($this->cleanText($data['delivery_type']), 'UTF-8');
        }
        if (isset($data['parcel_weight_kg']) && is_numeric($data['parcel_weight_kg'])) {
            $data['parcel_weight_kg'] = number_format(round((float) $data['parcel_weight_kg'], 3), 3, '.', '');
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
        $query = Parcel::query()->with(['sender', 'recipient', 'currentHub', 'statusLogs'])->latest();

        if ($search !== null) {
            $query->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('sender', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('recipient', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('currentHub', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('statusLogs', fn ($query) => $query->where('status', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    public function find(int $id): Parcel
    {
        return Parcel::query()
            ->with(['sender', 'recipient', 'currentHub', 'statusLogs.transitHub', 'payments'])
            ->findOrFail($id);
    }

    public function generateTrackingNumber(): string
    {
        $prefix = 'CXP-'.now()->format('Y').'-';
        $latest = Parcel::query()->where('tracking_number', 'like', $prefix.'%')->orderByDesc('tracking_number')->value('tracking_number');
        $next = is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) ? ((int) $matches[1]) + 1 : 1;

        do {
            $trackingNumber = $prefix.str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
        } while (Parcel::query()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    public function create(array $data): Parcel
    {
        $data = $this->sanitizeInput($data);
        $this->assertShipmentStatus($data['initial_status']);

        return DB::transaction(function () use ($data) {
            $sender = $this->customerService->findOrCreate($data['sender_name'], $data['sender_address']);
            $recipient = $this->customerService->findOrCreate($data['recipient_name'], $data['recipient_address']);
            $hub = $this->transitHubService->findOrCreate($data['current_hub_name'] ?? null);
            $weight = round((float) $data['parcel_weight_kg'], 3);
            $cost = $this->calculateShippingCost($weight, $data['delivery_type']);
            $trackingNumber = $data['tracking_number'] ?: $this->generateTrackingNumber();

            $parcel = Parcel::query()->create([
                'tracking_number' => $trackingNumber,
                'sender_customer_id' => $sender->id,
                'recipient_customer_id' => $recipient->id,
                'current_hub_id' => $hub?->id,
                'parcel_weight_kg' => $weight,
                'delivery_type' => $data['delivery_type'],
                'shipping_cost' => $cost,
            ]);

            $parcel->statusLogs()->create([
                'transit_hub_id' => $hub?->id,
                'sequence' => 1,
                'status' => $data['initial_status'],
                'occurred_at' => $this->parseDate($data['status_occurred_at'] ?? null),
            ]);
            $parcel->payments()->create(['amount' => $cost, 'payment_status' => 'pending']);

            return $parcel;
        });
    }

    public function update(Parcel $parcel, array $data): Parcel
    {
        $data = $this->sanitizeInput($data);

        return DB::transaction(function () use ($parcel, $data) {
            $sender = $this->customerService->findOrCreate($data['sender_name'], $data['sender_address']);
            $recipient = $this->customerService->findOrCreate($data['recipient_name'], $data['recipient_address']);
            $hub = $this->transitHubService->findOrCreate($data['current_hub_name'] ?? null);
            $weight = round((float) $data['parcel_weight_kg'], 3);
            $cost = $this->calculateShippingCost($weight, $data['delivery_type']);

            $parcel->update([
                'tracking_number' => $data['tracking_number'],
                'sender_customer_id' => $sender->id,
                'recipient_customer_id' => $recipient->id,
                'current_hub_id' => $hub?->id,
                'parcel_weight_kg' => $weight,
                'delivery_type' => $data['delivery_type'],
                'shipping_cost' => $cost,
            ]);
            $parcel->payments()->where('payment_status', 'pending')->update(['amount' => $cost]);

            return $parcel->refresh();
        });
    }

    public function delete(Parcel $parcel): void
    {
        $parcel->delete();
    }

    private function calculateShippingCost(float $weight, string $deliveryType): float
    {
        if (! isset(self::SPEED_MULTIPLIERS[$deliveryType])) {
            throw ValidationException::withMessages(['delivery_type' => 'Choose a supported delivery speed.']);
        }

        return round($weight * self::BASE_RATE_PER_KG * self::SPEED_MULTIPLIERS[$deliveryType], 2);
    }

    private function assertShipmentStatus(string $status): void
    {
        if (! in_array($status, self::SHIPMENT_STATUSES, true)) {
            throw ValidationException::withMessages(['initial_status' => 'Choose a valid initial shipment status.']);
        }
    }

    private function parseDate(?string $date): Carbon
    {
        return $date ? Carbon::parse($date) : now();
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }

    private function cleanAddress(mixed $address): string
    {
        return mb_convert_case($this->cleanText($address), MB_CASE_TITLE, 'UTF-8');
    }
}

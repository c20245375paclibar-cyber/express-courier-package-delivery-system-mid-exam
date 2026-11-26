<?php

namespace App\Services;

use App\Models\Parcel;
use App\Services\ShipmentStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

enum DeliveryType: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Overnight = 'overnight';
}

class ParcelService
{
    public const DELIVERY_TYPES = ['standard', 'express', 'overnight'];

    public const SHIPMENT_STATUSES = ['received', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'exception'];

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

    public static function findDuplicateContactFields(array $data): array
    {
        $seen = [];
        $duplicates = [];

        foreach (['sender_name', 'recipient_name', 'sender_address', 'recipient_address', 'current_hub_name'] as $field) {
            $value = $data[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)), 'UTF-8');
            if (isset($seen[$normalized])) {
                $duplicates[$field] = $seen[$normalized];
            } else {
                $seen[$normalized] = $field;
            }
        }

        return $duplicates;
    }

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly TransitHubService $transitHubService,
        private readonly ImageUploadService $imageUploads,
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
        }
        if (array_key_exists('current_hub_name', $data) && $data['current_hub_name'] === '') {
            $data['current_hub_name'] = null;
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

    public function paginate(?string $search = null, bool $archived = false): LengthAwarePaginator
    {
        $query = Parcel::query()
            ->select([
                'id',
                'tracking_number',
                'sender_customer_id',
                'recipient_customer_id',
                'current_hub_id',
                'parcel_weight_kg',
                'delivery_type',
                'shipping_cost',
            ])
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->with([
                'sender:id,name',
                'recipient:id,name',
                'currentHub:id,name',
                'statusLogs:id,parcel_id,status',
                'photos:id,parcel_id,path',
            ])
            ->latest();

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
            ->select([
                'id',
                'tracking_number',
                'sender_customer_id',
                'recipient_customer_id',
                'current_hub_id',
                'parcel_weight_kg',
                'delivery_type',
                'shipping_cost',
            ])
            ->with([
                'sender:id,name,address',
                'recipient:id,name,address',
                'currentHub:id,name',
                'statusLogs:id,parcel_id,transit_hub_id,sequence,status,occurred_at',
                'statusLogs.transitHub:id,name',
                'payments:id,parcel_id,amount,payment_status,payment_method,paid_at',
                'photos:id,parcel_id,path',
            ])
            ->findOrFail($id);
    }

    public function generateTrackingNumber(): string
    {
        $prefix = 'CXP-'.now()->format('Y').'-';
        $latest = Parcel::withTrashed()->where('tracking_number', 'like', $prefix.'%')->orderByDesc('tracking_number')->value('tracking_number');
        $next = is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) ? ((int) $matches[1]) + 1 : 1;

        do {
            $trackingNumber = $prefix.str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
        } while (Parcel::withTrashed()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    public function create(array $data): Parcel
    {
        $data = $this->sanitizeInput($data);
        $this->assertShipmentStatus($data['initial_status']);
        $uploads = $data['photos'] ?? [];
        unset($data['photos']);
        $photoPaths = $this->storeUploadedPhotos($uploads);

        try {
            return DB::transaction(function () use ($data, $photoPaths) {
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
                $this->attachPhotos($parcel, $photoPaths);

                return $parcel;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotos($photoPaths);
            throw $exception;
        }
    }

    public function update(Parcel $parcel, array $data): Parcel
    {
        $data = $this->sanitizeInput($data);
        $uploads = $data['photos'] ?? [];
        unset($data['photos']);

        if ($parcel->photos()->count() + count($uploads) > 5) {
            throw ValidationException::withMessages(['photos' => 'Each parcel can have up to 5 photos.']);
        }

        $photoPaths = $this->storeUploadedPhotos($uploads);

        try {
            return DB::transaction(function () use ($parcel, $data, $photoPaths) {
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
                $this->attachPhotos($parcel, $photoPaths);

                return $parcel->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotos($photoPaths);
            throw $exception;
        }
    }

    public function deletePhoto(Parcel $parcel, int $photoId): void
    {
        $photo = $parcel->photos()->findOrFail($photoId);
        $path = $photo->path;
        $photo->delete();

        $this->deleteStoredPhotos([$path]);
    }

    public function archive(Parcel $parcel): void
    {
        $parcel->delete();
    }

    public function restore(int $id): void
    {
        Parcel::query()->withTrashed()->findOrFail($id)->restore();
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

    private function storeUploadedPhotos(array $uploads): array
    {
        $paths = [];

        try {
            foreach ($uploads as $index => $upload) {
                if (! $upload instanceof UploadedFile) {
                    throw ValidationException::withMessages(['photos' => 'Every parcel photo must be a valid uploaded image.']);
                }

                $paths[] = $this->imageUploads->store($upload, 'parcels', 1920, false, "photos.{$index}");
            }
        } catch (Throwable $exception) {
            $this->deleteStoredPhotos($paths);
            throw $exception;
        }

        return $paths;
    }

    private function attachPhotos(Parcel $parcel, array $paths): void
    {
        foreach ($paths as $path) {
            $parcel->photos()->create(['path' => $path]);
        }
    }

    private function deleteStoredPhotos(array $paths): void
    {
        if ($paths !== [] && ! Storage::disk('public')->delete($paths)) {
            Log::warning('One or more parcel image files could not be deleted during cleanup.', ['paths' => $paths]);
        }
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

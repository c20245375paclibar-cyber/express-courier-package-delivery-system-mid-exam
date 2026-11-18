<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentStatusLogService
{
    public const STATUSES = [
        ShipmentStatus::Received->value,
        ShipmentStatus::PickedUp->value,
        ShipmentStatus::InTransit->value,
        ShipmentStatus::OutForDelivery->value,
        ShipmentStatus::Delivered->value,
        ShipmentStatus::Exception->value,
    ];

    public function __construct(private readonly TransitHubService $transitHubService) {}

    public function sanitizeInput(array $data): array
    {
        foreach (['current_hub_name', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanText($data[$field]);
            }
        }
        if (array_key_exists('status', $data)) {
            $data['status'] = mb_strtolower($data['status'], 'UTF-8');
        }
        if (array_key_exists('current_hub_name', $data) && $data['current_hub_name'] === '') {
            $data['current_hub_name'] = null;
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
        $query = ShipmentStatusLog::query()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->whereHas('parcel')
            ->with(['parcel', 'transitHub'])
            ->latest('occurred_at');

        if ($search !== null) {
            $query->where(function ($query) use ($search) {
                $query->where('status', 'like', "%{$search}%")
                    ->orWhereHas('parcel', fn ($query) => $query->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('transitHub', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    public function getFormOptions(): array
    {
        return [
            'parcels' => Parcel::query()->orderBy('tracking_number')->get(['id', 'tracking_number']),
            'shipmentStatuses' => self::STATUSES,
        ];
    }

    public function find(int $id): ShipmentStatusLog
    {
        return ShipmentStatusLog::query()->with(['parcel', 'transitHub'])->findOrFail($id);
    }

    public function create(array $data): ShipmentStatusLog
    {
        $data = $this->sanitizeInput($data);
        $this->assertStatus($data['status']);

        return DB::transaction(function () use ($data) {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($data['parcel_id']);
            $lastLog = $parcel->statusLogs()->withTrashed()->lockForUpdate()->reorder()->orderByDesc('sequence')->first();
            $occurredAt = $this->parseDate($data['occurred_at'] ?? null);
            $this->assertNotBeforePrevious($occurredAt, $lastLog);
            $hub = $this->transitHubService->findOrCreate($data['current_hub_name'] ?? null);

            $log = $parcel->statusLogs()->create([
                'transit_hub_id' => $hub?->id,
                'sequence' => ($lastLog?->sequence ?? 0) + 1,
                'status' => $data['status'],
                'occurred_at' => $occurredAt,
            ]);
            $parcel->update(['current_hub_id' => $hub?->id]);

            return $log;
        });
    }

    public function update(ShipmentStatusLog $log, array $data): ShipmentStatusLog
    {
        $data = $this->sanitizeInput($data);
        $this->assertStatus($data['status']);

        return DB::transaction(function () use ($log, $data) {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($log->parcel_id);
            $log = ShipmentStatusLog::query()->lockForUpdate()->findOrFail($log->id);
            $previous = $parcel->statusLogs()->where('sequence', '<', $log->sequence)->reorder()->orderByDesc('sequence')->first();
            $next = $parcel->statusLogs()->where('sequence', '>', $log->sequence)->orderBy('sequence')->first();
            $occurredAt = $this->parseDate($data['occurred_at']);
            $this->assertWithinBounds($occurredAt, $previous, $next);
            $hub = $this->transitHubService->findOrCreate($data['current_hub_name'] ?? null);

            $log->update([
                'transit_hub_id' => $hub?->id,
                'status' => $data['status'],
                'occurred_at' => $occurredAt,
            ]);
            if (! $next) {
                $parcel->update(['current_hub_id' => $hub?->id]);
            }

            return $log->refresh();
        });
    }

    public function archive(ShipmentStatusLog $log): void
    {
        DB::transaction(function () use ($log) {
            $parcel = Parcel::query()->withTrashed()->lockForUpdate()->findOrFail($log->parcel_id);
            $log->delete();
            $latest = $parcel->statusLogs()->orderByDesc('sequence')->first();
            $parcel->update(['current_hub_id' => $latest?->transit_hub_id]);
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $log = ShipmentStatusLog::query()->withTrashed()->lockForUpdate()->findOrFail($id);
            $parcel = Parcel::query()->withTrashed()->lockForUpdate()->findOrFail($log->parcel_id);
            $log->restore();

            if (! $parcel->statusLogs()->where('sequence', '>', $log->sequence)->exists()) {
                $parcel->update(['current_hub_id' => $log->transit_hub_id]);
            }
        });
    }

    private function parseDate(?string $date): Carbon
    {
        return $date ? Carbon::parse($date) : now();
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid shipment status.']);
        }
    }

    private function assertNotBeforePrevious(Carbon $occurredAt, ?ShipmentStatusLog $previous): void
    {
        if ($previous && $occurredAt->lt($previous->occurred_at)) {
            throw ValidationException::withMessages(['occurred_at' => 'The status date and time cannot precede the previous shipment event.']);
        }
    }

    private function assertWithinBounds(Carbon $occurredAt, ?ShipmentStatusLog $previous, ?ShipmentStatusLog $next): void
    {
        $this->assertNotBeforePrevious($occurredAt, $previous);
        if ($next && $occurredAt->gt($next->occurred_at)) {
            throw ValidationException::withMessages(['occurred_at' => 'The status date and time cannot follow the next shipment event.']);
        }
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }
}

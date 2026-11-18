<?php

namespace App\Services;

use App\Enums\CourierDispatchStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\ProcessCourierDispatch;
use App\Models\CourierDispatchRequest;
use App\Models\Parcel;
use App\Models\TransitHub;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CourierDispatchService
{
    public function sanitizeInput(array $data): array
    {
        if (array_key_exists('parcel_id', $data) && is_string($data['parcel_id'])) {
            $data['parcel_id'] = trim(strip_tags($data['parcel_id']));
        }

        return $data;
    }

    public function normalizeSearch(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = trim((string) preg_replace('/\s+/', ' ', strip_tags($search)));

        return $search === '' ? null : $search;
    }

    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return CourierDispatchRequest::query()
            ->with(['parcel:id,tracking_number', 'assignedHub:id,name'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('status', 'like', "%{$search}%")
                    ->orWhereHas('parcel', fn ($query) => $query->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('assignedHub', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('queued_at')
            ->paginate(10)
            ->withQueryString();
    }

    public function getFormOptions(): array
    {
        return [
            'parcels' => Parcel::query()->with(['sender:id,name', 'recipient:id,name'])->orderBy('tracking_number')
                ->get(['id', 'tracking_number', 'sender_customer_id', 'recipient_customer_id']),
        ];
    }

    public function find(int $id): CourierDispatchRequest
    {
        return CourierDispatchRequest::query()->with(['parcel.sender', 'parcel.recipient', 'assignedHub'])->findOrFail($id);
    }

    public function create(array $data): CourierDispatchRequest
    {
        $data = $this->sanitizeInput($data);
        $this->assertDispatchable((int) $data['parcel_id']);

        $request = CourierDispatchRequest::query()->create([
            'parcel_id' => $data['parcel_id'],
            'status' => CourierDispatchStatus::Pending,
            'queued_at' => now(),
        ]);

        $this->queue($request);

        return $request->refresh();
    }

    public function update(CourierDispatchRequest $request, array $data): CourierDispatchRequest
    {
        $data = $this->sanitizeInput($data);
        $this->assertDispatchable((int) $data['parcel_id']);

        DB::transaction(function () use ($request, $data): void {
            $request = CourierDispatchRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->update([
                'parcel_id' => $data['parcel_id'],
                'assigned_hub_id' => null,
                'status' => CourierDispatchStatus::Pending,
                'queued_at' => now(),
                'processed_at' => null,
                'failure_reason' => null,
            ]);

            $this->queue($request);
        });

        return $request->refresh();
    }

    public function delete(CourierDispatchRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            CourierDispatchRequest::query()->lockForUpdate()->findOrFail($request->id)->delete();
        });
    }

    public function process(int $requestId): void
    {
        $shouldProcess = DB::transaction(function () use ($requestId): bool {
            $request = CourierDispatchRequest::query()->lockForUpdate()->find($requestId);

            if (! $request || $request->status === CourierDispatchStatus::Assigned) {
                return false;
            }

            $request->update(['status' => CourierDispatchStatus::Processing, 'failure_reason' => null]);

            return true;
        });

        if (! $shouldProcess) {
            return;
        }

        DB::transaction(function () use ($requestId): void {
            $request = CourierDispatchRequest::query()->lockForUpdate()->find($requestId);

            if (! $request || $request->status === CourierDispatchStatus::Assigned) {
                return;
            }

            $parcel = Parcel::query()->lockForUpdate()->findOrFail($request->parcel_id);
            $this->assertParcelNotDelivered($parcel);

            $cursor = DB::table('dispatch_rotation_cursors')->where('id', 1)->lockForUpdate()->first();
            if (! $cursor) {
                throw new RuntimeException('Dispatch rotation cursor is missing.');
            }

            $hubs = TransitHub::query()->orderBy('id')->lockForUpdate()->get();
            if ($hubs->isEmpty()) {
                throw new RuntimeException('Create a transit hub before processing courier dispatch requests.');
            }

            $hubIndex = $hubs->search(fn (TransitHub $hub) => $hub->id === (int) $cursor->last_hub_id);
            $hub = $hubs->get($hubIndex === false ? 0 : ($hubIndex + 1) % $hubs->count());
            $lastLog = $parcel->statusLogs()->lockForUpdate()->reorder()->orderByDesc('sequence')->first();
            $occurredAt = now()->max($lastLog?->occurred_at ?? now());

            $parcel->update(['current_hub_id' => $hub->id]);
            $parcel->statusLogs()->create([
                'transit_hub_id' => $hub->id,
                'sequence' => ($lastLog?->sequence ?? 0) + 1,
                'status' => ShipmentStatus::InTransit->value,
                'occurred_at' => $occurredAt,
            ]);

            DB::table('dispatch_rotation_cursors')->where('id', 1)->update(['last_hub_id' => $hub->id]);
            $request->update([
                'assigned_hub_id' => $hub->id,
                'status' => CourierDispatchStatus::Assigned,
                'processed_at' => now(),
                'failure_reason' => null,
            ]);
        });
    }

    private function queue(CourierDispatchRequest $request): void
    {
        ProcessCourierDispatch::dispatch($request->id)->onConnection('database')->afterCommit();
    }

    private function assertDispatchable(int $parcelId): void
    {
        $parcel = Parcel::query()->findOrFail($parcelId);
        $this->assertParcelNotDelivered($parcel);
    }

    private function assertParcelNotDelivered(Parcel $parcel): void
    {
        if ($parcel->statusLogs()->reorder()->orderByDesc('sequence')->value('status') === ShipmentStatus::Delivered->value) {
            throw ValidationException::withMessages(['parcel_id' => 'A delivered parcel cannot be queued for dispatch.']);
        }
    }
}

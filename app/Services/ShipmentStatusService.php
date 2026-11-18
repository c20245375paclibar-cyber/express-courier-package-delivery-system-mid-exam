<?php

namespace App\Services;

use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShipmentStatusService
{
    public function __construct(private readonly CourierService $courierService) {}

    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return $this->courierService->paginateStatusLogs($this->courierService->normalizeSearch($search));
    }

    public function find(int $id): ShipmentStatusLog
    {
        return ShipmentStatusLog::query()->with(['parcel', 'transitHub'])->findOrFail($id);
    }

    public function create(array $data): ShipmentStatusLog
    {
        return $this->courierService->createStatusLog($data)->load(['parcel', 'transitHub']);
    }

    public function update(ShipmentStatusLog $log, array $data): ShipmentStatusLog
    {
        return $this->courierService->updateStatusLog($log, $data)->load(['parcel', 'transitHub']);
    }

    public function delete(ShipmentStatusLog $log): void
    {
        DB::transaction(function () use ($log) {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($log->parcel_id);
            $log = ShipmentStatusLog::query()->lockForUpdate()->findOrFail($log->id);
            $log->delete();

            $remainingLogs = $parcel->statusLogs()->orderBy('sequence')->lockForUpdate()->get();
            foreach ($remainingLogs as $index => $remainingLog) {
                $remainingLog->update(['sequence' => $index + 1]);
            }

            $parcel->update(['current_hub_id' => $remainingLogs->last()?->transit_hub_id]);
        });
    }
}

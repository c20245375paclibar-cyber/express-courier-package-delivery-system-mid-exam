<?php

namespace App\Services;

use App\Models\Parcel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ParcelService
{
    public function __construct(private readonly CourierService $courierService) {}

    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return $this->courierService->paginateParcels($this->courierService->normalizeSearch($search));
    }

    public function find(int $id): Parcel
    {
        return Parcel::query()
            ->with(['sender', 'recipient', 'currentHub', 'statusLogs.transitHub', 'payments'])
            ->findOrFail($id);
    }

    public function create(array $data): Parcel
    {
        return $this->courierService->createParcel($data)->load(['sender', 'recipient', 'currentHub', 'statusLogs.transitHub', 'payments']);
    }

    public function update(Parcel $parcel, array $data): Parcel
    {
        return $this->courierService->updateParcel($parcel, $data)
            ->load(['sender', 'recipient', 'currentHub', 'statusLogs.transitHub', 'payments']);
    }

    public function delete(Parcel $parcel): void
    {
        $parcel->delete();
    }
}

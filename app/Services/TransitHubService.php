<?php

namespace App\Services;

use App\Models\TransitHub;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransitHubService
{
    public function paginate(): LengthAwarePaginator
    {
        return TransitHub::query()
            ->withCount(['parcels', 'statusLogs'])
            ->orderBy('name')
            ->paginate(15);
    }

    public function find(int $id): TransitHub
    {
        return TransitHub::query()
            ->with(['parcels.sender', 'parcels.recipient', 'statusLogs.parcel'])
            ->findOrFail($id);
    }

    public function create(array $data): TransitHub
    {
        return TransitHub::query()->create($data);
    }

    public function update(TransitHub $hub, array $data): TransitHub
    {
        $hub->update($data);

        return $hub->refresh();
    }

    public function delete(TransitHub $hub): void
    {
        $hub->delete();
    }
}

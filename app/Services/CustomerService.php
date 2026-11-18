<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function paginate(): LengthAwarePaginator
    {
        return Customer::query()
            ->withCount(['sentParcels', 'receivedParcels'])
            ->latest()
            ->paginate(15);
    }

    public function find(int $id): Customer
    {
        return Customer::query()
            ->with(['sentParcels.recipient', 'sentParcels.currentHub', 'receivedParcels.sender', 'receivedParcels.currentHub'])
            ->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        if ($customer->sentParcels()->exists() || $customer->receivedParcels()->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'A customer linked to parcels cannot be deleted.',
            ]);
        }

        DB::transaction(fn () => $customer->delete());
    }
}

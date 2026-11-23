<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public static function isPlausibleName(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[\p{L}\p{M}]+(?:[ .\'’-][\p{L}\p{M}]+)*$/u', $value) === 1
            && preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $value) !== 1;
    }

    public static function isPlausibleAddress(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/[\p{L}]/u', $value) === 1
            && preg_match('/^[\p{L}\p{M}\d\s#.,\'’\/()\-]+$/u', $value) === 1
            && preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $value) !== 1;
    }

    public function sanitizeInput(array $data): array
    {
        foreach (['name', 'address'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $field === 'address'
                    ? $this->cleanAddress($data[$field])
                    : $this->cleanText($data[$field]);
            }
        }

        return $data;
    }

    public function normalizeSearch(mixed $search): ?string
    {
        return $this->normalizeQuery($search);
    }

    public function paginate(?string $search = null, bool $archived = false): LengthAwarePaginator
    {
        return Customer::query()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")))
            ->withCount(['sentParcels', 'receivedParcels'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): Customer
    {
        return Customer::query()
            ->with([
                'sentParcels:id,tracking_number,sender_customer_id,recipient_customer_id,current_hub_id,parcel_weight_kg,delivery_type,shipping_cost',
                'sentParcels.recipient:id,name,address',
                'sentParcels.currentHub:id,name',
                'receivedParcels:id,tracking_number,sender_customer_id,recipient_customer_id,current_hub_id,parcel_weight_kg,delivery_type,shipping_cost',
                'receivedParcels.sender:id,name,address',
                'receivedParcels.currentHub:id,name',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            return Customer::query()->create($this->sanitizeInput($data));
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $customer->update($this->sanitizeInput($data));

            return $customer->refresh();
        });
    }

    public function archive(Customer $customer): void
    {
        $customer->delete();
    }

    public function restore(int $id): void
    {
        Customer::query()->withTrashed()->findOrFail($id)->restore();
    }

    public function findOrCreate(string $name, string $address): Customer
    {
        $customer = Customer::withTrashed()->firstOrNew($this->sanitizeInput([
            'name' => $name,
            'address' => $address,
        ]));

        if ($customer->exists && $customer->trashed()) {
            $customer->restore();
        } elseif (! $customer->exists) {
            $customer->save();
        }

        return $customer;
    }

    private function normalizeQuery(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = $this->cleanText($search);

        return $search === '' ? null : $search;
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

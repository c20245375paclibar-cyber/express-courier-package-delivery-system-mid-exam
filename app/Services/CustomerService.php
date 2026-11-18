<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

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

    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return Customer::query()
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
            ->with(['sentParcels.recipient', 'sentParcels.currentHub', 'receivedParcels.sender', 'receivedParcels.currentHub'])
            ->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($this->sanitizeInput($data));
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($this->sanitizeInput($data));

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        if ($customer->sentParcels()->exists() || $customer->receivedParcels()->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'Customers linked to parcels cannot be deleted.',
            ]);
        }

        $customer->delete();
    }

    public function findOrCreate(string $name, string $address): Customer
    {
        return Customer::query()->firstOrCreate($this->sanitizeInput([
            'name' => $name,
            'address' => $address,
        ]));
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

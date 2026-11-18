<?php

namespace App\Services;

use App\Models\TransitHub;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransitHubService
{
    public static function isPlausibleName(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[\p{L}\p{M}\d]+(?:[ .\'’-][\p{L}\p{M}\d]+)*$/u', $value) === 1
            && preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $value) !== 1;
    }

    public function sanitizeInput(array $data): array
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = $this->cleanText($data['name']);
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
        return TransitHub::query()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->withCount(['parcels', 'statusLogs'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): TransitHub
    {
        return TransitHub::query()
            ->with(['parcels.sender', 'parcels.recipient', 'statusLogs.parcel'])
            ->findOrFail($id);
    }

    public function create(array $data): TransitHub
    {
        return TransitHub::query()->create($this->sanitizeInput($data));
    }

    public function update(TransitHub $hub, array $data): TransitHub
    {
        $hub->update($this->sanitizeInput($data));

        return $hub->refresh();
    }

    public function archive(TransitHub $hub): void
    {
        $hub->delete();
    }

    public function restore(int $id): void
    {
        TransitHub::query()->withTrashed()->findOrFail($id)->restore();
    }

    public function findOrCreate(?string $name): ?TransitHub
    {
        $name = $name === null ? '' : $this->cleanText($name);

        if ($name === '') {
            return null;
        }

        $hub = TransitHub::withTrashed()->firstOrNew(['name' => $name]);
        if ($hub->exists && $hub->trashed()) {
            $hub->restore();
        } elseif (! $hub->exists) {
            $hub->save();
        }

        return $hub;
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }
}

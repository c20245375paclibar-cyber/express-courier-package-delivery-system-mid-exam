<?php

namespace App\Models;

use App\Enums\CourierDispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourierDispatchRequest extends Model
{
    use SoftDeletes;

    protected $fillable = ['parcel_id', 'assigned_hub_id', 'status', 'queued_at', 'processed_at', 'failure_reason'];

    protected function casts(): array
    {
        return [
            'status' => CourierDispatchStatus::class,
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class)->withTrashed();
    }

    public function assignedHub(): BelongsTo
    {
        return $this->belongsTo(TransitHub::class, 'assigned_hub_id')->withTrashed();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parcel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tracking_number',
        'sender_customer_id',
        'recipient_customer_id',
        'current_hub_id',
        'parcel_weight_kg',
        'delivery_type',
        'shipping_cost',
    ];

    protected function casts(): array
    {
        return [
            'parcel_weight_kg' => 'decimal:3',
            'shipping_cost' => 'decimal:2',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sender_customer_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'recipient_customer_id')->withTrashed();
    }

    public function currentHub(): BelongsTo
    {
        return $this->belongsTo(TransitHub::class, 'current_hub_id')->withTrashed();
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShipmentStatusLog::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CourierPayment::class);
    }

    public function dispatchRequests(): HasMany
    {
        return $this->hasMany(CourierDispatchRequest::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ParcelPhoto::class);
    }
}

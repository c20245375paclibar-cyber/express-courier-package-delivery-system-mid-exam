<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransitHub extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'current_hub_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShipmentStatusLog::class);
    }

    public function dispatchRequests(): HasMany
    {
        return $this->hasMany(CourierDispatchRequest::class, 'assigned_hub_id');
    }
}

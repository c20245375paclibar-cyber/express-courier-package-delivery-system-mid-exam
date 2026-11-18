<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address'];

    public function sentParcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'sender_customer_id');
    }

    public function receivedParcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'recipient_customer_id');
    }
}

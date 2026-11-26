<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ParcelPhoto extends Model
{
    protected $fillable = ['path'];

    protected $hidden = ['path'];

    protected $appends = ['url'];

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

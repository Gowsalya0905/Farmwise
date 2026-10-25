<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plot extends Model
{
    protected $fillable = ['farm_id', 'name', 'area', 'area_unit'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
}

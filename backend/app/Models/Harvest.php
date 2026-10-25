<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Harvest extends Model
{
    protected $fillable = ['season_id', 'date', 'quantity', 'unit', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

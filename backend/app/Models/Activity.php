<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = ['season_id', 'date', 'type', 'notes'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}

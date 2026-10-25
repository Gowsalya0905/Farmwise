<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['season_id', 'date', 'category', 'amount', 'currency', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}

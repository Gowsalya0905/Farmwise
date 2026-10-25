<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['harvest_id', 'date', 'quantity', 'unit', 'unit_price', 'total', 'currency', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }
}

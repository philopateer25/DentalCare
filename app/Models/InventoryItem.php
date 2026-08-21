<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'name',
        'sku',
        'category',
        'unit',
        'min_reorder_level',
        'reorder_quantity',
    ];

    protected $casts = [
        'min_reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->batches()->sum('quantity_remaining');
    }
}

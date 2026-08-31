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
        'supplier_id',
        'name',
        'brand',
        'sku',
        'barcode',
        'category',
        'sub_category',
        'unit',
        'unit_price',
        'selling_price',
        'min_reorder_level',
        'reorder_quantity',
        'storage_location',
        'has_expiration',
        'description',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'min_reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'has_expiration' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->batches()->sum('quantity_remaining');
    }

    public function getEarliestExpiryAttribute()
    {
        return $this->batches()
            ->whereNotNull('expiry_date')
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->value('expiry_date');
    }

    public function getStockStatusAttribute(): string
    {
        $stock = $this->total_stock;
        if ($stock <= 0) {
            return 'Out of Stock';
        }
        if ($stock <= $this->min_reorder_level) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}

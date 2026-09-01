<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'supplier_id',
        'batch_number',
        'expiry_date',
        'received_date',
        'unit_cost',
        'quantity_received',
        'quantity_remaining',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_date' => 'date',
        'unit_cost' => 'decimal:2',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($batch) {
            if (empty($batch->batch_number)) {
                $batch->batch_number = 'BAT-' . date('Ymd') . '-' . str_pad((string) (static::max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProcedureConsumption::class);
    }

    public function getExpiryStatusAttribute(): string
    {
        if (! $this->expiry_date) {
            return 'No Expiry';
        }
        if ($this->expiry_date->isPast()) {
            return 'Expired';
        }
        if ($this->expiry_date->diffInDays(now()) <= 60) {
            return 'Expiring Soon';
        }

        return 'Active';
    }
}

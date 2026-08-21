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
        'batch_number',
        'expiry_date',
        'unit_cost',
        'quantity_received',
        'quantity_remaining',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'unit_cost' => 'decimal:2',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProcedureConsumption::class);
    }
}

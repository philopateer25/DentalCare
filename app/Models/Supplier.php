<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'name',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'website',
        'tax_number',
        'address',
        'payment_terms',
        'lead_time_days',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }
}

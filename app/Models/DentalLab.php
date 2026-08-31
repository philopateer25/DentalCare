<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DentalLab extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'name',
        'lab_type',
        'account_number',
        'contact_person',
        'phone',
        'email',
        'portal_url',
        'address',
        'standard_turnaround_days',
        'rating',
        'pricing_tier',
        'courier_service',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'standard_turnaround_days' => 'integer',
        'rating' => 'decimal:1',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class)->whereNotIn('status', ['seated_delivered', 'cancelled']);
    }

    public function getRedoRateAttribute(): float
    {
        $total = $this->labOrders()->count();
        if ($total === 0) {
            return 0.0;
        }
        $redos = $this->labOrders()->where('redo_count', '>', 0)->count();

        return round(($redos / $total) * 100, 1);
    }

    public function getTotalBilledAmountAttribute(): float
    {
        return (float) $this->labOrders()->sum('cost');
    }

    public function getPendingPayableAmountAttribute(): float
    {
        return (float) $this->labOrders()
            ->whereNotIn('payment_status', ['paid', 'warranty_covered'])
            ->sum('cost');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TreatmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'title',
        'status',
        'total_amount',
        'discount_amount',
        'net_amount',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(TreatmentPhase::class)->orderBy('sequence');
    }

    public function procedures(): HasManyThrough
    {
        return $this->hasManyThrough(TreatmentProcedure::class, TreatmentPhase::class);
    }

    public function recalculateTotals(): void
    {
        $this->load('phases.procedures');
        $total = 0;
        $discount = 0;

        foreach ($this->phases as $phase) {
            foreach ($phase->procedures as $procedure) {
                $total += $procedure->fee;
                $discount += $procedure->discount;
            }
        }

        $this->update([
            'total_amount' => $total,
            'discount_amount' => $discount,
            'net_amount' => max(0, $total - $discount),
        ]);
    }
}

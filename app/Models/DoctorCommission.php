<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'payment_id',
        'treatment_procedure_id',
        'gross_amount',
        'lab_deduction_amount',
        'commission_percentage',
        'commission_amount',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'lab_deduction_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TreatmentProcedure::class, 'treatment_procedure_id');
    }

    /**
     * Calculate commission amount from gross production and lab deductions
     */
    public static function calculateCommission(float $gross, float $labCost, float $percentage): float
    {
        $netProduction = max(0, $gross - $labCost);
        return round(($netProduction * $percentage) / 100, 2);
    }
}

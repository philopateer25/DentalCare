<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class DoctorCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'payroll_slip_id',
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

    protected static function booted(): void
    {
        static::creating(function (DoctorCommission $commission) {
            if (empty($commission->practice_id)) {
                $commission->practice_id = \Filament\Facades\Filament::getTenant()?->id 
                    ?? $commission->payment?->practice_id 
                    ?? $commission->doctor?->practice_id;
            }
        });
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function payrollSlip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class);
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

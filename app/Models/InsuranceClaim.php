<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'claim_number',
        'patient_id',
        'insurance_provider_id',
        'patient_insurance_policy_id',
        'invoice_id',
        'doctor_id',
        'claim_type',
        'total_claimed_amount',
        'estimated_insurance_amount',
        'patient_copay_amount',
        'actual_paid_amount',
        'eob_reference_number',
        'status',
        'submitted_at',
        'adjudicated_at',
        'denial_reason',
        'treatment_summary',
        'notes',
    ];

    protected $casts = [
        'total_claimed_amount' => 'decimal:2',
        'estimated_insurance_amount' => 'decimal:2',
        'patient_copay_amount' => 'decimal:2',
        'actual_paid_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'adjudicated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (empty($claim->claim_number)) {
                $claim->claim_number = 'CLM-' . date('Y') . '-' . str_pad((string) (static::max('id') + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PatientInsurancePolicy::class, 'patient_insurance_policy_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientInsurancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'patient_id',
        'insurance_provider_id',
        'policy_number',
        'group_number',
        'subscriber_name',
        'subscriber_relationship',
        'plan_type',
        'annual_maximum',
        'annual_deductible',
        'deductible_met',
        'preventive_coverage_pct',
        'basic_coverage_pct',
        'major_coverage_pct',
        'ortho_coverage_pct',
        'ortho_lifetime_max',
        'effective_date',
        'expiration_date',
        'is_active',
    ];

    protected $casts = [
        'annual_maximum' => 'decimal:2',
        'annual_deductible' => 'decimal:2',
        'deductible_met' => 'decimal:2',
        'preventive_coverage_pct' => 'decimal:2',
        'basic_coverage_pct' => 'decimal:2',
        'major_coverage_pct' => 'decimal:2',
        'ortho_coverage_pct' => 'decimal:2',
        'ortho_lifetime_max' => 'decimal:2',
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'is_active' => 'boolean',
    ];

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

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function getRemainingBenefitAttribute(): float
    {
        $used = $this->claims()->whereIn('status', ['approved_paid', 'under_review'])->sum('actual_paid_amount');

        return max(0, (float)$this->annual_maximum - $used);
    }
}

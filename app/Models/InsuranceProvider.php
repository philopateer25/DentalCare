<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'name',
        'payer_id',
        'contact_person',
        'phone',
        'claims_email',
        'portal_url',
        'claims_address',
        'standard_reimbursement_days',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'standard_reimbursement_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientInsurancePolicy::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }
}

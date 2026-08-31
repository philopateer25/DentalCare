<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'patient_id',
        'doctor_id',
        'treatment_plan_id',
        'dental_lab_id',
        'tooth_number_fdi',
        'shade',
        'material',
        'instructions',
        'cost',
        'status',
        'sent_at',
        'expected_delivery_at',
        'delivered_at',
    ];

    protected $casts = [
        'tooth_number_fdi' => 'integer',
        'cost' => 'decimal:2',
        'sent_at' => 'datetime',
        'expected_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function dentalLab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }
}

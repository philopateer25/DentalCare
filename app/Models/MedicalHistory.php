<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'blood_type',
        'diabetic_status',
        'cardiac_history',
        'hypertension_status',
        'bleeding_disorder',
        'latex_allergy',
        'penicillin_allergy',
        'local_anesthetic_allergy',
        'glaucoma_history',
        'cataract_history',
        'dry_eye_syndrome',
        'wears_contact_lenses',
        'vision_correction_history',
        'medical_conditions_json',
        'active_medications_json',
        'notes',
    ];

    protected $casts = [
        'diabetic_status' => 'boolean',
        'cardiac_history' => 'boolean',
        'hypertension_status' => 'boolean',
        'bleeding_disorder' => 'boolean',
        'latex_allergy' => 'boolean',
        'penicillin_allergy' => 'boolean',
        'local_anesthetic_allergy' => 'boolean',
        'glaucoma_history' => 'boolean',
        'cataract_history' => 'boolean',
        'dry_eye_syndrome' => 'boolean',
        'wears_contact_lenses' => 'boolean',
        'vision_correction_history' => 'boolean',
        'medical_conditions_json' => 'array',
        'active_medications_json' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

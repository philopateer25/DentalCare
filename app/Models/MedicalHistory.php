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
        'diabetes_type',
        'hba1c_level',
        'blood_sugar_reading',
        'diabetes_medications',
        'hypoglycemia_history',
        'cardiac_history',
        'cardiac_conditions_json',
        'requires_antibiotic_prophylaxis',
        'hypertension_status',
        'blood_pressure_stage',
        'typical_blood_pressure',
        'bleeding_disorder',
        'anticoagulant_type',
        'inr_level',
        'bisphosphonate_therapy',
        'bisphosphonate_details',
        'dental_anxiety_level',
        'severe_gag_reflex',
        'pregnancy_trimester',
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
        'hypoglycemia_history' => 'boolean',
        'diabetes_medications' => 'array',
        'cardiac_history' => 'boolean',
        'cardiac_conditions_json' => 'array',
        'requires_antibiotic_prophylaxis' => 'boolean',
        'hypertension_status' => 'boolean',
        'bleeding_disorder' => 'boolean',
        'inr_level' => 'decimal:2',
        'bisphosphonate_therapy' => 'boolean',
        'severe_gag_reflex' => 'boolean',
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

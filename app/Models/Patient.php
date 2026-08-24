<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'file_number',
        'full_name',
        'first_name',
        'last_name',
        'national_id',
        'phone',
        'secondary_phone',
        'dob',
        'gender',
        'blood_type',
        'referral_source',
        'whatsapp_number',
        'email',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'penicillin_allergy',
        'latex_allergy',
        'bleeding_disorder',
        'cardiac_condition',
        'hypertension',
        'diabetic',
        'hepatitis',
        'pregnant',
        'medical_alerts_json',
        'medical_notes',
        'status',
    ];

    protected $casts = [
        'dob' => 'date',
        'penicillin_allergy' => 'boolean',
        'latex_allergy' => 'boolean',
        'bleeding_disorder' => 'boolean',
        'cardiac_condition' => 'boolean',
        'hypertension' => 'boolean',
        'diabetic' => 'boolean',
        'hepatitis' => 'boolean',
        'pregnant' => 'boolean',
        'medical_alerts_json' => 'array',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(MedicalHistory::class);
    }

    public function toothRecords(): HasMany
    {
        return $this->hasMany(ToothRecord::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(PatientTooth::class);
    }

    public function perioChartings(): HasMany
    {
        return $this->hasMany(PerioCharting::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    /**
     * Get or set active medical alert keys array for Filament CheckboxList component
     */
    public function medicalAlerts(): Attribute
    {
        return Attribute::make(
            get: function () {
                $alerts = [];
                $flags = [
                    'penicillin_allergy',
                    'latex_allergy',
                    'bleeding_disorder',
                    'cardiac_condition',
                    'hypertension',
                    'diabetic',
                    'hepatitis',
                    'pregnant',
                ];

                foreach ($flags as $flag) {
                    if (!empty($this->attributes[$flag])) {
                        $alerts[] = $flag;
                    }
                }

                return $alerts;
            },
            set: function ($value) {
                $value = is_array($value) ? $value : [];
                $flags = [
                    'penicillin_allergy',
                    'latex_allergy',
                    'bleeding_disorder',
                    'cardiac_condition',
                    'hypertension',
                    'diabetic',
                    'hepatitis',
                    'pregnant',
                ];

                $updated = [];
                foreach ($flags as $flag) {
                    $updated[$flag] = in_array($flag, $value);
                }

                return $updated;
            }
        );
    }
}

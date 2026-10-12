<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTreatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'procedure_title',
        'tooth_number_fdi',
        'performed_year',
        'clinic_or_doctor_name',
        'notes',
        'attachments_json',
    ];

    protected $casts = [
        'tooth_number_fdi' => 'integer',
        'attachments_json' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTooth extends Model
{
    use HasFactory;

    protected $table = 'patient_teeth';

    protected $fillable = [
        'patient_id',
        'tooth_number',
        'condition',
        'notes',
        'surfaces',
    ];

    protected $casts = [
        'surfaces' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

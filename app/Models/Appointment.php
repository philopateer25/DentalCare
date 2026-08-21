<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'branch_id',
        'operatory_id',
        'patient_id',
        'doctor_id',
        'treatment_procedure_id',
        'start_time',
        'end_time',
        'chief_complaint',
        'status',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function operatory(): BelongsTo
    {
        return $this->belongsTo(Operatory::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TreatmentProcedure::class, 'treatment_procedure_id');
    }
}

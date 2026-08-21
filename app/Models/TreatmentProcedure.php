<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentProcedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_phase_id',
        'tooth_number_fdi',
        'surface',
        'procedure_code_id',
        'doctor_id',
        'fee',
        'discount',
        'net_amount',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'tooth_number_fdi' => 'integer',
        'fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TreatmentPhase::class, 'treatment_phase_id');
    }

    public function procedureCode(): BelongsTo
    {
        return $this->belongsTo(ProcedureCode::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProcedureConsumption::class);
    }
}

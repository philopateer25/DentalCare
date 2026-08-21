<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_plan_id',
        'sequence',
        'name',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(TreatmentProcedure::class);
    }
}

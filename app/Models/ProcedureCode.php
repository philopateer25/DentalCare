<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'title',
        'description',
        'standard_fee',
        'estimated_duration_minutes',
    ];

    protected $casts = [
        'standard_fee' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcedureCategory::class, 'category_id');
    }

    public function treatmentProcedures(): HasMany
    {
        return $this->hasMany(TreatmentProcedure::class);
    }
}

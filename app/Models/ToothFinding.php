<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToothFinding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clinical_details' => 'array',
    ];

    public function examination(): BelongsTo
    {
        return $this->belongsTo(DentalExamination::class, 'dental_examination_id');
    }

    public function surfaceFindings(): HasMany
    {
        return $this->hasMany(ToothSurfaceFinding::class);
    }
}

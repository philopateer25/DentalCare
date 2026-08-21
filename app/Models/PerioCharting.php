<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerioCharting extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'exam_date',
        'examined_by_doctor_id',
        'probe_depths_json',
        'bleeding_on_probing_json',
        'gingival_margins_json',
        'plaque_index_json',
        'notes',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'probe_depths_json' => 'array',
        'bleeding_on_probing_json' => 'array',
        'gingival_margins_json' => 'array',
        'plaque_index_json' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examined_by_doctor_id');
    }
}

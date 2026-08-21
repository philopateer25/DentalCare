<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurfaceCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'tooth_record_id',
        'surface',
        'condition_code',
        'status',
        'material',
        'notes',
    ];

    public function toothRecord(): BelongsTo
    {
        return $this->belongsTo(ToothRecord::class);
    }
}

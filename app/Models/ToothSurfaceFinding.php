<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToothSurfaceFinding extends Model
{
    protected $guarded = [];

    public function toothFinding(): BelongsTo
    {
        return $this->belongsTo(ToothFinding::class);
    }
}

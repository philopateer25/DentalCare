<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a clinic's own dropdown list (Phase 5). */
class PracticeOption extends Model
{
    protected $fillable = ['practice_id', 'list', 'value', 'use_count', 'last_used_at'];

    protected $casts = [
        'use_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
}

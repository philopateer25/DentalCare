<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operatory extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Operatory $operatory) {
            $branch = $operatory->branch ?? Branch::find($operatory->branch_id);
            $practice = $branch?->practice;
            if ($practice && $practice->chair_limit > 0) {
                $currentChairs = static::whereHas('branch', fn ($q) => $q->where('practice_id', $practice->id))->count();
                if ($currentChairs >= $practice->chair_limit) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'name' => "Clinic chair limit reached ({$practice->chair_limit}/{$practice->chair_limit}). Please contact software support to upgrade your chair capacity.",
                    ]);
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function practice(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Practice::class,
            Branch::class,
            'id',
            'id',
            'branch_id',
            'practice_id'
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

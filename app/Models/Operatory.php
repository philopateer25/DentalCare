<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Validation\ValidationException;

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
        // Only ACTIVE rooms count towards the chair limit, so switching a room off
        // frees a place. The limit comes from the license (central app) when set.
        static::creating(function (Operatory $operatory) {
            if ($operatory->is_active ?? true) {
                static::guardChairLimit($operatory);
            }
        });

        static::updating(function (Operatory $operatory) {
            if ($operatory->isDirty('is_active') && $operatory->is_active) {
                static::guardChairLimit($operatory);
            }
        });
    }

    private static function guardChairLimit(Operatory $operatory): void
    {
        $branch = $operatory->branch ?? Branch::find($operatory->branch_id);
        $practice = $branch?->practice;
        $limit = $practice?->effectiveChairLimit();

        if ($limit !== null && $practice->activeChairCount() >= $limit) {
            throw ValidationException::withMessages([
                'name' => __('Chair limit reached (:used of :limit). Switch another room off, or ask DOXamigo to raise your chair limit.', ['used' => $practice->activeChairCount(), 'limit' => $limit]),
            ]);
        }
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function practice(): HasOneThrough
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

    public function maintenanceRecords(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceRecord::class, 'maintenance_record_operatory');
    }
}

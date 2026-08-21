<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToothRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'tooth_number_fdi',
        'mobility_class',
        'furcation_grade',
        'is_missing',
        'is_impacted',
        'notes',
    ];

    protected $casts = [
        'tooth_number_fdi' => 'integer',
        'mobility_class' => 'integer',
        'furcation_grade' => 'integer',
        'is_missing' => 'boolean',
        'is_impacted' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function surfaceConditions(): HasMany
    {
        return $this->hasMany(SurfaceCondition::class);
    }

    /**
     * Convert FDI tooth number to Universal Tooth Notation
     */
    public function getUniversalNumberAttribute(): string
    {
        return static::fdiToUniversal($this->tooth_number_fdi);
    }

    public static function fdiToUniversal(int $fdi): string
    {
        $fdiMap = [
            // Adult Permanent Teeth (11-18, 21-28, 31-38, 41-48)
            18 => '1', 17 => '2', 16 => '3', 15 => '4', 14 => '5', 13 => '6', 12 => '7', 11 => '8',
            21 => '9', 22 => '10', 23 => '11', 24 => '12', 25 => '13', 26 => '14', 27 => '15', 28 => '16',
            38 => '17', 37 => '18', 36 => '19', 35 => '20', 34 => '21', 33 => '22', 32 => '23', 31 => '24',
            41 => '25', 42 => '26', 43 => '27', 44 => '28', 45 => '29', 46 => '30', 47 => '31', 48 => '32',
            // Pediatric Primary Teeth (51-55, 61-65, 71-75, 81-85)
            55 => 'A', 54 => 'B', 53 => 'C', 52 => 'D', 51 => 'E',
            61 => 'F', 62 => 'G', 63 => 'H', 64 => 'I', 65 => 'J',
            75 => 'K', 74 => 'L', 73 => 'M', 72 => 'N', 71 => 'O',
            81 => 'P', 82 => 'Q', 83 => 'R', 84 => 'S', 85 => 'T',
        ];

        return $fdiMap[$fdi] ?? (string)$fdi;
    }
}

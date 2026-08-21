<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DentalLab extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }
}

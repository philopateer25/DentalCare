<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'practice_id',
        'specialty',
        'license_number',
        'default_commission_percentage',
        'bio',
    ];

    protected $casts = [
        'default_commission_percentage' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
}

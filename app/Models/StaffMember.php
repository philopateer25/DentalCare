<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'user_id',
        'employee_id',
        'first_name',
        'last_name',
        'role',
        'email',
        'phone',
        'national_id',
        'employment_type',
        'hire_date',
        'base_salary',
        'hourly_rate',
        'bank_name',
        'bank_account_number',
        'tax_id',
        'emergency_contact',
        'is_active',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'hire_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($staff) {
            if (empty($staff->employee_id)) {
                $staff->employee_id = 'EMP-' . str_pad((string) (static::max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function ($staff) {
            $staff->base_salary = $staff->base_salary !== null && $staff->base_salary !== '' ? $staff->base_salary : 0.00;
            $staff->hourly_rate = $staff->hourly_rate !== null && $staff->hourly_rate !== '' ? $staff->hourly_rate : 0.00;
        });
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

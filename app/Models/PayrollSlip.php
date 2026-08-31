<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'staff_member_id',
        'payslip_number',
        'pay_period_month',
        'pay_period_year',
        'base_salary',
        'overtime_amount',
        'bonus_amount',
        'allowance_amount',
        'tax_deduction',
        'insurance_deduction',
        'other_deductions',
        'net_salary',
        'payment_method',
        'status',
        'disbursed_at',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'tax_deduction' => 'decimal:2',
        'insurance_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($slip) {
            if (empty($slip->payslip_number)) {
                $month = str_pad((string) $slip->pay_period_month, 2, '0', STR_PAD_LEFT);
                $slip->payslip_number = "PAY-{$slip->pay_period_year}-{$month}-" . str_pad((string) (static::max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public static function calculateNet(float $base, float $overtime, float $bonus, float $allowance, float $tax, float $insurance, float $other): float
    {
        $gross = $base + $overtime + $bonus + $allowance;
        $deductions = $tax + $insurance + $other;

        return max(0, round($gross - $deductions, 2));
    }
}

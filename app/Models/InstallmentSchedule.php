<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'installment_plan_id',
        'schedule_number',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'payment_date',
    ];

    protected $casts = [
        'schedule_number' => 'integer',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

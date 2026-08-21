<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'invoice_id',
        'installment_schedule_id',
        'patient_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'logged_by_user_id',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installmentSchedule(): BelongsTo
    {
        return $this->belongsTo(InstallmentSchedule::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }

    public function doctorCommissions(): HasMany
    {
        return $this->hasMany(DoctorCommission::class);
    }
}

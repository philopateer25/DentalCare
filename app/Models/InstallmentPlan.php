<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'total_funded_amount',
        'down_payment',
        'number_of_installments',
        'frequency',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_funded_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'number_of_installments' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class)->orderBy('schedule_number');
    }
}

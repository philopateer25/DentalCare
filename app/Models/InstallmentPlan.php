<?php

namespace App\Models;

use Carbon\Carbon;
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

    public function getRemainingBalanceAttribute(): float
    {
        $paid = (float) $this->schedules()->sum('paid_amount') + (float) $this->down_payment;

        return max(0.0, (float) $this->total_funded_amount - $paid);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_funded_amount <= 0) {
            return 100.0;
        }
        $paid = (float) $this->schedules()->sum('paid_amount') + (float) $this->down_payment;

        return min(100.0, round(($paid / $this->total_funded_amount) * 100, 1));
    }

    public function generateSchedules(?Carbon $startDate = null): void
    {
        $startDate = $startDate ?? now()->addMonth();
        $principal = (float) $this->total_funded_amount - (float) $this->down_payment;
        $count = max(1, (int) $this->number_of_installments);
        $installmentAmount = round($principal / $count, 2);

        $this->schedules()->delete();

        $currentDate = $startDate->copy();
        for ($i = 1; $i <= $count; $i++) {
            $amount = ($i === $count) ? ($principal - ($installmentAmount * ($count - 1))) : $installmentAmount;

            $this->schedules()->create([
                'schedule_number' => $i,
                'due_date' => $currentDate->format('Y-m-d'),
                'amount' => $amount,
                'paid_amount' => 0.00,
                'status' => 'pending',
            ]);

            if ($this->frequency === 'weekly') {
                $currentDate->addWeek();
            } elseif ($this->frequency === 'bi_weekly') {
                $currentDate->addWeeks(2);
            } else {
                $currentDate->addMonth();
            }
        }
    }
}

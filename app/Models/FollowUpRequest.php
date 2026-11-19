<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A doctor's request to reception to book a follow-up for a patient.
 */
class FollowUpRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_CANCELLED = 'cancelled';

    /** Timeframe key => [label, days from today]. */
    public const TIMEFRAMES = [
        'asap' => ['As soon as possible', 1],
        '1_week' => ['In 1 week', 7],
        '2_weeks' => ['In 2 weeks', 14],
        '1_month' => ['In 1 month', 30],
        '3_months' => ['In 3 months', 90],
        '6_months' => ['In 6 months (recall)', 182],
        'date' => ['On a specific date', null],
    ];

    protected $fillable = [
        'practice_id', 'patient_id', 'doctor_id', 'source_appointment_id', 'timeframe', 'preferred_date',
        'duration_minutes', 'procedure_name', 'notes', 'urgent', 'status', 'booked_appointment_id',
        'handled_by', 'handled_at', 'handled_note',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'urgent' => 'boolean',
        'handled_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function bookedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'booked_appointment_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function timeframeLabel(): string
    {
        if ($this->timeframe === 'date' && $this->preferred_date) {
            return 'On '.$this->preferred_date->format('D j M');
        }

        return self::TIMEFRAMES[$this->timeframe][0] ?? $this->timeframe;
    }

    /** The date reception should aim for (a weekday), used to pre-fill the booking form. */
    public function suggestedDate(): Carbon
    {
        $date = $this->timeframe === 'date' && $this->preferred_date
            ? $this->preferred_date->copy()
            : now()->startOfDay()->addDays(self::TIMEFRAMES[$this->timeframe][1] ?? 7);

        return Carbon::instance($date)->setTime(10, 0);
    }
}

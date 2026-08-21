<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'patient_id',
        'treatment_plan_id',
        'invoice_number',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'issue_date',
        'due_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

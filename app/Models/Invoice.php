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
        'subtotal',
        'discount_amount',
        'tax_amount',
        'insurance_covered_amount',
        'patient_copay_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'issue_date',
        'due_date',
        'terms_and_conditions',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'insurance_covered_amount' => 'decimal:2',
        'patient_copay_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'INV-' . date('Y') . '-' . str_pad((string) (static::max('id') + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('total_price');
        $total = $subtotal - $this->discount_amount + $this->tax_amount;
        $paid = $this->payments()->sum('amount');
        $balance = max(0, $total - $paid);

        $status = 'unpaid';
        if ($paid >= $total && $total > 0) {
            $status = 'paid';
        } elseif ($paid > 0 && $paid < $total) {
            $status = 'partially_paid';
        } elseif ($this->due_date && $this->due_date->isPast() && $balance > 0) {
            $status = 'overdue';
        }

        $this->updateQuietly([
            'subtotal' => $subtotal,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'status' => $status,
        ]);
    }
}

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

        static::saving(function (Invoice $invoice) {
            // Check if items exist
            if ($invoice->exists && $invoice->items()->exists()) {
                $invoice->subtotal = (float) $invoice->items()->sum('total_price');
                $invoice->total_amount = max(0, (float)$invoice->subtotal - (float)$invoice->discount_amount + (float)$invoice->tax_amount);
            } else {
                if (empty($invoice->subtotal) && !empty($invoice->total_amount)) {
                    $invoice->subtotal = $invoice->total_amount;
                } elseif (!empty($invoice->subtotal) && empty($invoice->total_amount)) {
                    $invoice->total_amount = max(0, (float)$invoice->subtotal - (float)$invoice->discount_amount + (float)$invoice->tax_amount);
                }
            }

            // Sync paid amount from payments if payments exist
            if ($invoice->exists && $invoice->payments()->exists()) {
                $invoice->paid_amount = (float) $invoice->payments()->sum('amount');
            }

            // Always recalculate balance due: Total - Paid
            $invoice->balance_due = max(0, (float)$invoice->total_amount - (float)$invoice->paid_amount);

            // Automatically set status based on paid vs total if not manually cancelled
            if ($invoice->status !== 'cancelled') {
                if ((float)$invoice->paid_amount >= (float)$invoice->total_amount && (float)$invoice->total_amount > 0) {
                    $invoice->status = 'paid';
                } elseif ((float)$invoice->paid_amount > 0 && (float)$invoice->paid_amount < (float)$invoice->total_amount) {
                    $invoice->status = 'partially_paid';
                } elseif ((float)$invoice->paid_amount == 0) {
                    $invoice->status = 'unpaid';
                }
            }
        });

        static::saved(function (Invoice $invoice) {
            // If invoice has paid_amount > 0 and payments table has less, auto-create the payment record
            $existingPaymentsSum = (float) $invoice->payments()->sum('amount');
            $paidAmount = (float) $invoice->paid_amount;
            if ($paidAmount > $existingPaymentsSum) {
                $difference = $paidAmount - $existingPaymentsSum;
                Payment::create([
                    'practice_id' => $invoice->practice_id ?? \Filament\Facades\Filament::getTenant()?->id ?? 1,
                    'invoice_id' => $invoice->id,
                    'patient_id' => $invoice->patient_id,
                    'amount' => $difference,
                    'payment_method' => 'cash',
                    'transaction_reference' => 'PAY-' . date('Ymd') . '-' . str_pad((string) (Payment::max('id') + 1), 4, '0', STR_PAD_LEFT),
                    'logged_by_user_id' => auth()->id() ?? 1,
                    'paid_at' => now(),
                    'notes' => 'Payment registered with invoice ' . $invoice->invoice_number,
                ]);
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
        if ($this->items()->exists()) {
            $subtotal = (float) $this->items()->sum('total_price');
            $total = max(0, $subtotal - (float)$this->discount_amount + (float)$this->tax_amount);
        } else {
            $subtotal = (float) $this->subtotal;
            $total = (float) $this->total_amount;
        }

        $paid = (float) $this->payments()->sum('amount');
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

    public function getRemainingBalanceAttribute(): float
    {
        return (float) ($this->balance_due ?? 0);
    }
}

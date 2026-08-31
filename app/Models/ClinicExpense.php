<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'expense_number',
        'category',
        'payee',
        'supplier_id',
        'dental_lab_id',
        'amount',
        'expense_date',
        'payment_method',
        'reference_number',
        'tax_deductible',
        'is_recurring',
        'recurring_frequency',
        'receipt_url',
        'logged_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'tax_deductible' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = 'EXP-' . date('Y') . '-' . str_pad((string) (static::max('id') + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dentalLab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}

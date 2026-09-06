<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'treatment_procedure_id',
        'procedure_name',
        'tooth_number',
        'quantity',
        'unit_price',
        'total',
        'doctor_commission_percentage',
        'doctor_commission_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TreatmentProcedure::class, 'treatment_procedure_id');
    }
}

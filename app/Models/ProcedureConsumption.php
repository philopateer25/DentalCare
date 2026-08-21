<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_procedure_id',
        'inventory_batch_id',
        'quantity_consumed',
    ];

    protected $casts = [
        'quantity_consumed' => 'integer',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TreatmentProcedure::class, 'treatment_procedure_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}

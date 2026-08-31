<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'tracking_number',
        'patient_id',
        'doctor_id',
        'dental_lab_id',
        'order_type',
        'sub_type',
        'tooth_number_fdi',
        'teeth_fdi',
        'shade',
        'shade_system',
        'stump_shade',
        'translucency',
        'surface_texture',
        'occlusal_staining',
        'margin_design',
        'material',
        'impression_type',
        'digital_scan_url',
        'instructions',
        'cost',
        'patient_charge',
        'lab_invoice_number',
        'payment_status',
        'status',
        'redo_reason',
        'redo_count',
        'warranty_years',
        'qc_passed',
        'lab_box_number',
        'sent_at',
        'expected_delivery_at',
        'delivered_at',
        'fitting_appointment_id',
        'fitting_date',
    ];

    protected $casts = [
        'tooth_number_fdi' => 'integer',
        'cost' => 'decimal:2',
        'patient_charge' => 'decimal:2',
        'redo_count' => 'integer',
        'warranty_years' => 'integer',
        'qc_passed' => 'boolean',
        'sent_at' => 'datetime',
        'expected_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'fitting_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->tracking_number)) {
                $order->tracking_number = 'LAB-' . date('Y') . '-' . str_pad((string) (static::max('id') + 1), 5, '0', STR_PAD_LEFT);
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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function dentalLab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class);
    }

    public function fittingAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'fitting_appointment_id');
    }

    public function isOverdue(): bool
    {
        return $this->expected_delivery_at 
            && $this->expected_delivery_at->isPast() 
            && ! in_array($this->status, ['received_at_clinic', 'seated_delivered', 'cancelled']);
    }

    public function hasFittingConflict(): bool
    {
        // If fitting appointment is scheduled before case is delivered or expected
        if (! in_array($this->status, ['received_at_clinic', 'seated_delivered']) && $this->fitting_date) {
            return $this->fitting_date->lte($this->expected_delivery_at ?? now());
        }

        return false;
    }
}

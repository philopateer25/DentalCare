<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAppointmentRequest extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_appointment_requests';

    protected $fillable = [
        'practice_id',
        'patient_id',
        'requested_date',
        'requested_time',
        'notes',
        'status',
    ];

    public function practice()
    {
        return $this->belongsTo(Practice::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}

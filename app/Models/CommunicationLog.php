<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    protected $fillable = [
        'practice_id',
        'patient_id',
        'direction',
        'type',
        'message',
        'status',
        'message_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function practice()
    {
        return $this->belongsTo(Practice::class);
    }
}

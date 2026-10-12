<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_id',
        'patient_id',
        'user_id',
        'tooth_number_fdi',
        'treatment_procedure_id',
        'title',
        'file_path',
        'disk',
        'mime_type',
        'file_size_bytes',
        'duration_seconds',
        'share_token',
        'token_expires_at',
        'is_revoked',
        'whatsapp_status',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_revoked' => 'boolean',
        'file_size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'tooth_number_fdi' => 'integer',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TreatmentProcedure::class, 'treatment_procedure_id');
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function isValidForStreaming(): bool
    {
        return !$this->is_revoked && !$this->isExpired();
    }
}

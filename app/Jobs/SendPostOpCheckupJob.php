<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\Appointment;
use App\Services\WhatsAppService;
use App\Models\Practice;
use Illuminate\Support\Facades\Log;

class SendPostOpCheckupJob implements ShouldQueue
{
    use Queueable;

    protected Appointment $appointment;

    /**
     * Create a new job instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load required relations if not already loaded
        $this->appointment->loadMissing(['patient', 'doctor']);

        $patient = $this->appointment->patient;
        $doctorName = $this->appointment->doctor->name ?? 'Doctor';
        $clinicName = Practice::find(1)->name ?? 'Our Dental Clinic';
        
        $phone = $patient->phone ?? '';

        if (empty($phone)) {
            Log::warning("Cannot send Post-Op WhatsApp: Patient ID {$patient->id} has no phone number.");
            return;
        }

        $procedure = $this->appointment->procedure_name 
            ?: ($this->appointment->procedure->name ?? 'dental procedure');

        $message = "Hi {$patient->first_name}, this is a quick check-up from {$clinicName} following your {$procedure} today with Dr. {$doctorName}. How are you feeling? Reply to this message if you need anything or have any concerns! 🦷✨";

        $success = WhatsAppService::sendMessage($phone, $message);

        if (!$success) {
            Log::error("Failed to send Post-Op WhatsApp to Patient ID {$patient->id}.");
        }
    }
}

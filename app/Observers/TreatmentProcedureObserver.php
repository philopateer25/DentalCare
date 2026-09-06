<?php

namespace App\Observers;

use App\Models\TreatmentProcedure;
use App\Services\WhatsAppService;

class TreatmentProcedureObserver
{
    /**
     * Handle the TreatmentProcedure "updated" event.
     */
    public function updated(TreatmentProcedure $procedure): void
    {
        // If status changed to completed
        if ($procedure->wasChanged('status') && $procedure->status === 'completed') {
            
            // Record completed time if not set
            if (!$procedure->completed_at) {
                $procedure->updateQuietly(['completed_at' => now()]);
            }

            $patient = $procedure->phase->plan->patient;
            $code = $procedure->procedureCode->code;
            
            // Check if patient has a phone number
            if (!$patient || !$patient->phone) {
                return;
            }

            // Route based on procedure code category/type
            // E.g., D7140 = Extraction, D6010 = Implant, D3310 = Root Canal
            $message = "";
            
            if (str_starts_with($code, 'D7')) { // Surgery/Extraction
                $message = "Hi {$patient->first_name}, following your extraction today, please remember to bite firmly on the gauze for 30 minutes. Do not rinse, spit, or drink through a straw for 24 hours. Take pain medication as prescribed. Let us know if you experience severe pain or swelling.";
            } elseif (str_starts_with($code, 'D6')) { // Implant
                $message = "Hi {$patient->first_name}, following your implant surgery, please apply an ice pack to your cheek (15 mins on/off) today. Eat only soft foods and avoid chewing on the implant side. Please take all prescribed antibiotics.";
            } elseif (str_starts_with($code, 'D3')) { // Endodontics (Root Canal)
                $message = "Hi {$patient->first_name}, following your root canal today, it is normal to experience some tenderness. Please chew on the opposite side until you get your final crown. Take pain relievers as instructed.";
            }

            if (!empty($message)) {
                // Dispatch or send synchronously 
                // For a real app, this should be a queued Job. We'll send it directly here using the service for simplicity.
                WhatsAppService::sendMessage($patient->phone, $message);
            }
        }
    }
}

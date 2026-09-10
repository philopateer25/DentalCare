<?php

namespace App\Notifications;

use App\Models\TreatmentProcedure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ProcedureCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TreatmentProcedure $procedure
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if (class_exists(WebPushChannel::class)) {
            $channels[] = WebPushChannel::class;
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        $procedureName = $this->procedure->procedure_name ?? 'Dental Procedure';
        $patientName = $this->procedure->phase?->plan?->patient?->full_name ?? 'Patient';
        $practiceId = $this->procedure->phase?->plan?->patient?->practice_id;
        $msg = "Procedure '{$procedureName}' for patient {$patientName} has been marked as completed.";

        return [
            'type' => 'procedure_completed',
            'procedure_id' => $this->procedure->id,
            'procedure_name' => $procedureName,
            'practice_id' => $practiceId,
            'patient_name' => $patientName,
            'title' => 'Procedure Completed',
            'message' => $msg,
            'dedup_key' => "procedure_completed_{$this->procedure->id}",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toWebPush($notifiable, $notification)
    {
        $data = $this->toArray($notifiable);
        return (new WebPushMessage)
            ->title($data['title'])
            ->icon('/images/icon-512x512.png')
            ->body($data['message']);
    }
}

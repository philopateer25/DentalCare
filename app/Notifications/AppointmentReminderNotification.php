<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AppointmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
        public ?string $customMessage = null
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
        $patientName = $this->appointment->patient?->full_name ?? 'Patient';
        $timeStr = $this->appointment->start_time ? $this->appointment->start_time->format('M d, Y h:i A') : 'Scheduled Time';
        $msg = $this->customMessage ?? "Appointment reminder for {$patientName} scheduled at {$timeStr}.";

        return [
            'type' => 'appointment_reminder',
            'appointment_id' => $this->appointment->id,
            'practice_id' => $this->appointment->practice_id,
            'patient_name' => $patientName,
            'doctor_name' => $this->appointment->doctor?->name,
            'start_time' => $this->appointment->start_time?->toIso8601String(),
            'title' => 'Appointment Reminder',
            'message' => $msg,
            'dedup_key' => "appointment_reminder_{$this->appointment->id}",
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
            ->body($data['message'])
            ->action('View Calendar', 'view_calendar');
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SystemAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $alertMessage,
        public ?int $practiceId = null,
        public string $level = 'info'
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
        return [
            'type' => 'system_alert',
            'title' => $this->title,
            'message' => $this->alertMessage,
            'practice_id' => $this->practiceId,
            'level' => $this->level,
            'dedup_key' => "system_alert_" . md5($this->title . $this->alertMessage),
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

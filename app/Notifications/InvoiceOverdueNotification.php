<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice
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
        $patientName = $this->invoice->patient?->full_name ?? 'Patient';
        $amountStr = number_format($this->invoice->balance_due, 2);
        $msg = "Invoice #{$this->invoice->invoice_number} for {$patientName} is overdue. Balance due: {$amountStr}.";

        return [
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'practice_id' => $this->invoice->practice_id,
            'patient_name' => $patientName,
            'balance_due' => (float) $this->invoice->balance_due,
            'title' => 'Invoice Overdue Alert',
            'message' => $msg,
            'dedup_key' => "invoice_overdue_{$this->invoice->id}",
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

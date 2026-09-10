<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LowInventoryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
        public int $currentStock
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
        $msg = "Item '{$this->item->name}' is low in stock ({$this->currentStock} remaining, min reorder level: {$this->item->min_reorder_level}).";

        return [
            'type' => 'low_inventory',
            'inventory_item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'practice_id' => $this->item->practice_id,
            'current_stock' => $this->currentStock,
            'min_reorder_level' => $this->item->min_reorder_level,
            'title' => 'Low Stock Warning',
            'message' => $msg,
            'dedup_key' => "low_inventory_{$this->item->id}",
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

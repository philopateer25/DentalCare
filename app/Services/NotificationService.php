<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Practice;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\LowInventoryNotification;
use App\Notifications\ProcedureCompletedNotification;
use App\Notifications\SystemAlertNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Send notification to recipients ensuring no duplicate notification is sent within 24 hours.
     */
    public static function sendUnique(User|Collection|array $recipients, Notification $notification): int
    {
        if ($recipients instanceof User) {
            $recipients = collect([$recipients]);
        } elseif (is_array($recipients)) {
            $recipients = collect($recipients);
        }

        $sentCount = 0;
        $dummyNotifiable = new User();
        $data = method_exists($notification, 'toArray') ? $notification->toArray($dummyNotifiable) : [];
        $dedupKey = $data['dedup_key'] ?? null;

        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }

            if ($dedupKey) {
                $alreadyNotified = $user->notifications()
                    ->where('created_at', '>=', now()->subHours(24))
                    ->get()
                    ->contains(function ($n) use ($dedupKey) {
                        return isset($n->data['dedup_key']) && $n->data['dedup_key'] === $dedupKey;
                    });

                if ($alreadyNotified) {
                    continue;
                }
            }

            $user->notify($notification);
            $sentCount++;
        }

        return $sentCount;
    }

    /**
     * Dispatch appointment reminder to doctor & practice staff.
     */
    public static function notifyAppointmentReminder(Appointment $appointment, ?string $customMessage = null): int
    {
        $practiceUsers = User::where('practice_id', $appointment->practice_id)->get();
        return self::sendUnique($practiceUsers, new AppointmentReminderNotification($appointment, $customMessage));
    }

    /**
     * Dispatch invoice overdue alert.
     */
    public static function notifyInvoiceOverdue(Invoice $invoice): int
    {
        $practiceUsers = User::where('practice_id', $invoice->practice_id)->get();
        return self::sendUnique($practiceUsers, new InvoiceOverdueNotification($invoice));
    }

    /**
     * Dispatch low inventory warning.
     */
    public static function notifyLowInventory(InventoryItem $item, int $currentStock): int
    {
        $practiceUsers = User::where('practice_id', $item->practice_id)->get();
        return self::sendUnique($practiceUsers, new LowInventoryNotification($item, $currentStock));
    }

    /**
     * Dispatch procedure completion alert.
     */
    public static function notifyProcedureCompleted(TreatmentProcedure $procedure): int
    {
        $practiceId = $procedure->phase?->plan?->patient?->practice_id;
        if (!$practiceId) {
            return 0;
        }

        $practiceUsers = User::where('practice_id', $practiceId)->get();
        return self::sendUnique($practiceUsers, new ProcedureCompletedNotification($procedure));
    }

    /**
     * Dispatch system alert.
     */
    public static function notifySystemAlert(int $practiceId, string $title, string $message, string $level = 'info'): int
    {
        $practiceUsers = User::where('practice_id', $practiceId)->get();
        return self::sendUnique($practiceUsers, new SystemAlertNotification($title, $message, $practiceId, $level));
    }
}

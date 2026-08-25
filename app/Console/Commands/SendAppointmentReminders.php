<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 24-hour WhatsApp reminders for upcoming appointments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        $appointments = Appointment::with('patient', 'doctor')
            ->whereBetween('start_time', [$tomorrowStart, $tomorrowEnd])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->get();

        $count = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->patient && $appointment->patient->phone) {
                $time = $appointment->start_time->format('h:i A');
                $doctorName = $appointment->doctor ? $appointment->doctor->name : 'our doctor';
                $message = "Hello {$appointment->patient->full_name}, this is a friendly reminder for your dental appointment tomorrow at {$time} with Dr. {$doctorName}. Please let us know if you need to reschedule. Thank you!";
                
                SendWhatsAppMessage::dispatch($appointment->patient->phone, $message);
                $count++;
            }
        }

        $this->info("Dispatched {$count} appointment reminders.");
    }
}

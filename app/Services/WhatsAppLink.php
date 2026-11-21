<?php

namespace App\Services;

use App\Http\Controllers\Api\PatientVoiceNoteController;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\Prescription;
use Filament\Facades\Filament;

/**
 * Manual WhatsApp messages open WhatsApp (Web on desktop, the app on phones) with a ready-made
 * message; staff review it and press send. Nothing is sent from the server.
 *
 * Automated messages (procedure observer, post-op job, WhatsApp bot) still use WhatsAppService.
 */
class WhatsAppLink
{
    /** Template keys shown in menus. */
    public const TEMPLATES = [
        'appointment_confirmation' => 'Appointment confirmation',
        'appointment_reminder' => 'Appointment reminder',
        'post_op_checkup' => 'Post-op check-up',
        'balance_reminder' => 'Balance reminder',
        'chart_summary' => 'Dental chart summary',
        'greeting' => 'General message',
    ];

    public static function phone(?string $raw): string
    {
        return PatientVoiceNoteController::normalizePhoneNumber($raw);
    }

    /** wa.me opens WhatsApp Web on desktop and the WhatsApp app on phones. */
    public static function url(?string $rawPhone, string $text): ?string
    {
        $phone = self::phone($rawPhone);

        return $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($text) : null;
    }

    public static function forPatient(Patient $patient, string $template, array $context = []): ?string
    {
        return self::url($patient->whatsapp_number ?: $patient->phone, self::message($template, $patient, $context));
    }

    public static function forAppointment(Appointment $appointment, string $template): ?string
    {
        $patient = $appointment->patient;
        if (! $patient) {
            return null;
        }

        return self::forPatient($patient, $template, [
            'date' => $appointment->start_time?->translatedFormat('l j F'),
            'time' => $appointment->start_time?->format('h:i A'),
            'doctor' => $appointment->doctor?->name,
            'practice' => $appointment->practice ?? null,
        ]);
    }

    public static function forPrescription(Prescription $rx): ?string
    {
        $patient = $rx->patient;
        if (! $patient) {
            return null;
        }

        $lines = array_filter([
            "💊 {$rx->medication_name} {$rx->dosage}",
            $rx->frequency ? "⏱ {$rx->frequency}" : null,
            $rx->duration ? "📅 {$rx->duration}" : null,
            $rx->instructions ? "📝 {$rx->instructions}" : null,
        ]);

        return self::forPatient($patient, 'prescription', [
            'doctor' => $rx->doctor?->name,
            'details' => implode("\n", $lines),
        ]);
    }

    public static function message(string $template, Patient $patient, array $context = []): string
    {
        $practice = $context['practice'] ?? Filament::getTenant() ?? $patient->practice;
        $clinic = $practice instanceof Practice ? $practice->name : 'our clinic';
        $name = $patient->first_name ?: 'there';
        $ar = app()->getLocale() === 'ar';
        $date = $context['date'] ?? '';
        $time = $context['time'] ?? '';
        $doctor = $context['doctor'] ?? null;
        $balance = $context['balance'] ?? null;

        return match ($template) {
            'appointment_confirmation' => $ar
                ? "مرحباً {$name}، تم تأكيد موعدك في {$clinic} يوم {$date} الساعة {$time}".($doctor ? " مع {$doctor}" : '').'. نتطلع لرؤيتك!'
                : "Hello {$name}, your appointment at {$clinic} is confirmed for {$date} at {$time}".($doctor ? " with {$doctor}" : '').'. We look forward to seeing you!',
            'appointment_reminder' => $ar
                ? "مرحباً {$name}، تذكير بموعدك في {$clinic} يوم {$date} الساعة {$time}. يرجى الرد للتأكيد أو لتغيير الموعد."
                : "Hello {$name}, a reminder of your appointment at {$clinic} on {$date} at {$time}. Please reply to confirm or to reschedule.",
            'post_op_checkup' => $ar
                ? "مرحباً {$name}، معك {$clinic}. نطمئن عليك بعد الإجراء الأخير. إذا كان لديك أي ألم أو استفسار يرجى الرد على هذه الرسالة."
                : "Hello {$name}, this is {$clinic}. We are checking in to see how you are feeling after your recent procedure. Reply here if you have any pain or questions.",
            'balance_reminder' => $ar
                ? "مرحباً {$name}، تذكير لطيف من {$clinic} بأن لديك رصيداً مستحقاً".($balance ? " قدره {$balance}" : '').'. يمكنك السداد في زيارتك القادمة أو عبر إنستاباي.'
                : "Hello {$name}, a friendly reminder from {$clinic} that you have an outstanding balance".($balance ? " of {$balance}" : '').'. You can pay at your next visit or via InstaPay.',
            'chart_summary' => $ar
                ? "مرحباً {$name}، مرفق ملخص مخطط أسنانك وخطة العلاج من {$clinic}."
                : "Hello {$name}, please find attached your dental chart summary and treatment plan from {$clinic}.",
            'prescription' => ($ar ? "🏥 {$clinic} - روشتة\n" : "🏥 {$clinic} - Prescription\n")
                .($doctor ? "👨‍⚕️ {$doctor}\n" : '')."\n".($context['details'] ?? '')
                .($ar ? "\n\nمع تمنياتنا بالشفاء العاجل!" : "\n\nGet well soon!"),
            default => $ar ? "مرحباً {$name}، معك {$clinic}." : "Hello {$name}, this is {$clinic}.",
        };
    }
}

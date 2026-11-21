<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Services\WhatsAppLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function egyptian_numbers_are_normalised_into_wa_me_links(): void
    {
        $this->assertSame('201011112222', WhatsAppLink::phone('010 1111 2222'));
        $this->assertSame('201011112222', WhatsAppLink::phone('+20 101 111 2222'));
        $this->assertSame('201011112222', WhatsAppLink::phone('00201011112222'));
        $this->assertNull(WhatsAppLink::url('', 'hi'));

        $url = WhatsAppLink::url('01011112222', 'Hello & welcome');
        $this->assertSame('https://wa.me/201011112222?text=Hello%20%26%20welcome', $url);
    }

    #[Test]
    public function templates_use_patient_clinic_and_appointment_details(): void
    {
        $practice = Practice::create(['name' => 'Smile Clinic', 'currency' => 'EGP', 'is_active' => true]);
        $branch = Branch::create(['practice_id' => $practice->id, 'name' => 'Main']);
        $room = Operatory::create(['branch_id' => $branch->id, 'name' => 'Room 1']);
        $doctor = User::create(['practice_id' => $practice->id, 'name' => 'Dr. Mona', 'email' => 'm@s.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $patient = Patient::create(['practice_id' => $practice->id, 'file_number' => 'W1', 'first_name' => 'Ali', 'last_name' => 'Omar', 'phone' => '01099998888', 'gender' => 'male']);
        $appointment = Appointment::create([
            'practice_id' => $practice->id, 'branch_id' => $branch->id, 'operatory_id' => $room->id,
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => 'booked',
            'start_time' => now()->setTime(10, 30), 'end_time' => now()->setTime(11, 0),
        ]);

        $text = rawurldecode(explode('?text=', WhatsAppLink::forAppointment($appointment, 'appointment_confirmation'))[1]);
        $this->assertStringContainsString('Hello Ali', $text);
        $this->assertStringContainsString('Smile Clinic', $text);
        $this->assertStringContainsString('10:30 AM', $text);
        $this->assertStringContainsString('Dr. Mona', $text);

        $balance = rawurldecode(explode('?text=', WhatsAppLink::forPatient($patient, 'balance_reminder', ['balance' => 'EGP 500.00']))[1]);
        $this->assertStringContainsString('EGP 500.00', $balance);

        app()->setLocale('ar');
        $this->assertStringContainsString('مرحباً Ali', WhatsAppLink::message('greeting', $patient));
    }
}

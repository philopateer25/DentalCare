<?php

namespace Tests\Feature;

use App\Models\ClinicExpense;
use App\Models\DoctorCommission;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\User;
use App\Services\DailyCloseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyCloseReportTest extends TestCase
{
    use RefreshDatabase;

    private function practice(string $name): Practice
    {
        return Practice::create([
            'name' => $name, 'currency' => 'EGP', 'is_active' => true,
            'onboarding_completed_at' => now(), 'onboarding_step' => 6,
        ]);
    }

    private function invoice(Practice $practice, Patient $patient, float $total, float $paid = 0): Invoice
    {
        return Invoice::create([
            'practice_id' => $practice->id, 'patient_id' => $patient->id,
            'subtotal' => $total, 'discount_amount' => 0, 'total_amount' => $total,
            'paid_amount' => $paid, 'balance_due' => $total - $paid, 'status' => 'unpaid', 'issue_date' => now(),
        ]);
    }

    #[Test]
    public function it_sums_one_clinic_day(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 22, 18, 0, 0, 'Africa/Cairo'));

        $practice = $this->practice('Smile');
        $other = $this->practice('Other');
        $doctor = User::create(['name' => 'Dr. Sara', 'email' => 'sara@smile.test', 'password' => bcrypt('x'), 'practice_id' => $practice->id]);
        $patient = Patient::create(['practice_id' => $practice->id, 'first_name' => 'Mona', 'last_name' => 'Adel', 'phone' => '01000000001', 'gender' => 'female']);
        $outsider = Patient::create(['practice_id' => $other->id, 'first_name' => 'Out', 'last_name' => 'Sider', 'phone' => '01000000002', 'gender' => 'male']);

        $inv = $this->invoice($practice, $patient, 1000);
        $cash = Payment::create(['practice_id' => $practice->id, 'invoice_id' => $inv->id, 'patient_id' => $patient->id, 'amount' => 300, 'payment_method' => 'cash', 'paid_at' => now()]);
        Payment::create(['practice_id' => $practice->id, 'invoice_id' => $inv->id, 'patient_id' => $patient->id, 'amount' => 200, 'payment_method' => 'instapay', 'paid_at' => now()->subHour()]);
        // Yesterday and another clinic: not counted.
        Payment::create(['practice_id' => $practice->id, 'invoice_id' => $inv->id, 'patient_id' => $patient->id, 'amount' => 50, 'payment_method' => 'cash', 'paid_at' => now()->subDay()]);
        $otherInv = $this->invoice($other, $outsider, 999);
        Payment::create(['practice_id' => $other->id, 'invoice_id' => $otherInv->id, 'patient_id' => $outsider->id, 'amount' => 999, 'payment_method' => 'cash', 'paid_at' => now()]);

        ClinicExpense::create(['practice_id' => $practice->id, 'category' => 'supplies', 'payee' => 'Shop', 'amount' => 80, 'expense_date' => now()->toDateString(), 'payment_method' => 'cash']);

        $plan = InstallmentPlan::create(['invoice_id' => $inv->id, 'total_funded_amount' => 400, 'down_payment' => 0, 'number_of_installments' => 2, 'frequency' => 'monthly', 'status' => 'active']);
        InstallmentSchedule::create(['installment_plan_id' => $plan->id, 'schedule_number' => 1, 'due_date' => now()->subDays(5)->toDateString(), 'amount' => 200, 'paid_amount' => 50, 'status' => 'partially_paid']);
        InstallmentSchedule::create(['installment_plan_id' => $plan->id, 'schedule_number' => 2, 'due_date' => now()->toDateString(), 'amount' => 200, 'paid_amount' => 0, 'status' => 'pending']);

        DoctorCommission::create(['practice_id' => $practice->id, 'doctor_id' => $doctor->id, 'payment_id' => $cash->id, 'gross_amount' => 300, 'lab_deduction_amount' => 0, 'commission_percentage' => 40, 'commission_amount' => 120, 'status' => 'accrued']);

        $r = app(DailyCloseReport::class)->build($practice->id);

        $this->assertSame('2026-11-22', $r['date']);
        $this->assertEquals(['cash' => 300.0, 'instapay' => 200.0], $r['collected']);
        $this->assertSame(500.0, $r['collected_total']);
        $this->assertSame(2, $r['payments_count']);
        $this->assertSame(80.0, $r['expenses_total']);
        $this->assertSame(220.0, $r['net_cash'], 'Cash collected minus cash expenses.');
        $this->assertSame(450.0, $r['outstanding_total'], '1000 − (300 + 200 + 50), own clinic only.');
        $this->assertSame(200.0, $r['installments_due_today']);
        $this->assertSame(150.0, $r['installments_overdue']);
        $this->assertSame([['doctor' => 'Dr. Sara', 'amount' => 120.0]], $r['commissions']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DentalExamination;
use App\Models\Patient;
use App\Models\PatientTooth;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Dental\OdontogramRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChartWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practice;

    protected User $doctor;

    protected Patient $patient;

    protected DentalExamination $exam;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['doctor', 'clinic_admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
        $this->practice = Practice::create(['name' => 'Chart Clinic', 'currency' => 'EGP', 'is_active' => true, 'features' => ['3d_model']]);
        $this->doctor = $this->user('doc@chart.test', 'doctor');
        $this->patient = Patient::create([
            'practice_id' => $this->practice->id, 'file_number' => 'CW-1', 'first_name' => 'Nour', 'last_name' => 'Adel',
            'phone' => '0100', 'gender' => 'female',
        ]);
        $this->exam = DentalExamination::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'initial']);

        $cat = ProcedureCategory::create(['name' => 'Restorative', 'code' => 'REST']);
        ProcedureCode::create(['category_id' => $cat->id, 'code' => 'D2391', 'title' => 'Composite posterior', 'standard_fee' => 900]);
    }

    protected function user(string $email, string $role, ?Practice $practice = null): User
    {
        $u = User::create([
            'practice_id' => ($practice ?? $this->practice)->id, 'name' => ucfirst($role), 'email' => $email,
            'password' => bcrypt('password'), 'role' => $role,
        ]);
        $u->assignRole($role);

        return $u;
    }

    #[Test]
    public function workspace_payload_has_patient_alerts_exams_records_and_codes(): void
    {
        $this->actingAs($this->doctor)->getJson("/api/patients/{$this->patient->id}/chart-workspace")
            ->assertOk()
            ->assertJsonPath('patient.name', 'Nour Adel')
            ->assertJsonPath('current_exam_id', $this->exam->id)
            ->assertJsonPath('can_edit_pricing', false)
            ->assertJsonStructure(['alerts', 'examinations', 'records', 'procedures_by_tooth', 'plans', 'procedure_codes']);
    }

    #[Test]
    public function workspace_is_tenant_scoped_and_requires_login(): void
    {
        $this->getJson("/api/patients/{$this->patient->id}/chart-workspace")->assertUnauthorized();

        $other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true]);
        $outsider = $this->user('out@other.test', 'doctor', $other);
        $this->actingAs($outsider)->getJson("/api/patients/{$this->patient->id}/chart-workspace")->assertForbidden();
        $this->actingAs($outsider)->get("/patients/{$this->patient->id}/chart/print")->assertForbidden();
    }

    #[Test]
    public function legacy_diagnosis_in_notes_is_read_as_structured_fields(): void
    {
        PatientTooth::create([
            'patient_id' => $this->patient->id, 'tooth_number' => '16', 'condition' => 'active_caries',
            'notes' => '[Dx: Reversible Pulpitis] [Severity: Moderate] Patient avoids cold drinks',
        ]);

        $this->actingAs($this->doctor)->getJson("/api/patients/{$this->patient->id}/chart-workspace")
            ->assertJsonPath('records.16.clinical_details.diagnosis', 'Reversible Pulpitis')
            ->assertJsonPath('records.16.clinical_details.severity', 'Moderate')
            ->assertJsonPath('records.16.notes', 'Patient avoids cold drinks');

        $json = OdontogramRules::normaliseRecord('{"diagnosis":"Pulpal Necrosis","urgency":"Urgent"}', null);
        $this->assertSame('Pulpal Necrosis', $json['clinical_details']['diagnosis']);
        $this->assertNull($json['notes']);
    }

    #[Test]
    public function only_admins_can_change_the_fee_schedule(): void
    {
        $code = ProcedureCode::first();
        $this->actingAs($this->doctor)->patchJson("/api/procedure-codes/{$code->id}", ['standard_fee' => 1])->assertForbidden();

        $admin = $this->user('admin@chart.test', 'clinic_admin');
        $this->actingAs($admin)->patchJson("/api/procedure-codes/{$code->id}", ['standard_fee' => 950])->assertOk();
        $this->assertEquals(950, (float) $code->fresh()->standard_fee);
    }

    #[Test]
    public function completing_a_filling_turns_carious_surfaces_into_composite(): void
    {
        $this->actingAs($this->doctor)->postJson("/api/examinations/{$this->exam->id}/findings", [
            'tooth_number' => '36', 'condition' => 'active_caries', 'status' => 'needs_treatment',
            'surfaces' => ['occlusal' => 'active_caries', 'mesial' => 'healthy'],
        ])->assertOk();

        $plan = TreatmentPlan::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'title' => 'P', 'status' => 'draft']);
        $phase = $plan->phases()->create(['sequence' => 1, 'name' => 'Phase 1']);
        $proc = $phase->procedures()->create([
            'tooth_number_fdi' => 36, 'procedure_code_id' => ProcedureCode::where('code', 'D2391')->value('id'),
            'fee' => 900, 'net_amount' => 900, 'status' => 'planned',
        ]);

        $this->actingAs($this->doctor)->patchJson("/api/procedures/{$proc->id}/complete")->assertOk();

        $this->actingAs($this->doctor)->getJson("/api/examinations/{$this->exam->id}/odontogram?normalised=1")
            ->assertJsonPath('36.condition', 'composite_filled')
            ->assertJsonPath('36.status', 'existing')
            ->assertJsonPath('36.surfaces.occlusal', 'composite_filled')
            ->assertJsonPath('36.surfaces.mesial', 'healthy');
    }

    #[Test]
    public function print_summary_and_legacy_details_redirect(): void
    {
        $this->actingAs($this->doctor)->get("/patients/{$this->patient->id}/chart/print")
            ->assertOk()
            ->assertSee('Nour Adel');

        $this->actingAs($this->doctor)->get("/patients/{$this->patient->id}/odontogram-details?tooth=21")
            ->assertRedirect()
            ->assertRedirectContains("/patients/{$this->patient->id}/chart?tooth=21");
    }
}

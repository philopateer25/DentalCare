<?php

namespace Tests\Feature;

use App\Models\DentalExamination;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Services\Dental\OdontogramRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Behaviour of the 3D odontogram's clinical logic (charting, suggestions, plans, completion).
 */
class OdontogramLogicTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected DentalExamination $exam;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'doctor']);
        $practice = Practice::create(['name' => 'Logic Clinic', 'currency' => 'EGP', 'is_active' => true, 'features' => ['3d_model']]);
        $this->doctor = User::create([
            'practice_id' => $practice->id,
            'name' => 'Dr. Logic',
            'email' => 'logic@clinic.test',
            'password' => bcrypt('password'),
            'role' => 'doctor',
        ]);
        $this->doctor->assignRole('doctor');
        $this->patient = Patient::create([
            'practice_id' => $practice->id,
            'file_number' => 'LOG-001',
            'first_name' => 'Mona',
            'last_name' => 'Test',
            'phone' => '0100000000',
            'gender' => 'female',
        ]);
        $this->exam = DentalExamination::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'type' => 'initial',
        ]);

        $cat = ProcedureCategory::create(['name' => 'Restorative', 'code' => 'REST']);
        foreach ([
            ['D2330', 'Composite - anterior', 800],
            ['D2391', 'Composite - posterior', 900],
            ['D2740', 'Crown - ceramic', 4500],
            ['D3110', 'Pulp cap - direct', 300],
            ['D6010', 'Implant body', 12000],
            ['D7140', 'Extraction', 700],
        ] as [$code, $title, $fee]) {
            ProcedureCode::create(['category_id' => $cat->id, 'code' => $code, 'title' => $title, 'standard_fee' => $fee]);
        }
    }

    protected function chart(string $tooth, string $condition, string $status)
    {
        return $this->actingAs($this->doctor)->postJson("/api/examinations/{$this->exam->id}/findings", [
            'tooth_number' => $tooth,
            'condition' => $condition,
            'status' => $status,
        ]);
    }

    #[Test]
    public function marking_needs_treatment_does_not_create_a_plan_but_returns_a_suggestion(): void
    {
        $response = $this->chart('36', 'active_caries', 'needs_treatment')->assertOk();

        $this->assertSame(0, TreatmentPlan::count());
        $this->assertSame(0, TreatmentProcedure::count());
        $response->assertJsonPath('suggestion.code', 'D2391'); // posterior tooth -> posterior composite
        $this->assertDatabaseHas('patient_teeth', ['patient_id' => $this->patient->id, 'tooth_number' => '36', 'status' => 'needs_treatment']);
    }

    #[Test]
    public function suggestions_are_clinically_sensible(): void
    {
        $this->chart('11', 'active_caries', 'needs_treatment')->assertJsonPath('suggestion.code', 'D2330');
        $this->chart('46', 'missing', 'needs_treatment')->assertJsonPath('suggestion.code', 'D6010'); // never an extraction
        $this->chart('26', 'root_canal', 'needs_treatment')->assertJsonPath('suggestion.code', 'D2740');
        $this->chart('21', 'healthy', 'needs_treatment')->assertJsonPath('suggestion', null);
        $this->chart('16', 'active_caries', 'watch')->assertJsonPath('suggestion', null);
    }

    #[Test]
    public function invalid_teeth_and_conditions_are_rejected(): void
    {
        $this->chart('99', 'active_caries', 'existing')->assertUnprocessable();
        $this->chart('11', 'banana', 'existing')->assertUnprocessable();
    }

    #[Test]
    public function viewing_the_active_plan_does_not_create_one(): void
    {
        $this->actingAs($this->doctor)->getJson("/api/patients/{$this->patient->id}/treatment-plans/active")->assertOk();

        $this->assertSame(0, TreatmentPlan::count());
    }

    #[Test]
    public function chart_apis_require_login(): void
    {
        $this->getJson("/api/patients/{$this->patient->id}/examinations")->assertUnauthorized();
        $this->postJson("/api/examinations/{$this->exam->id}/findings", ['tooth_number' => '11', 'condition' => 'healthy'])->assertUnauthorized();
        $this->getJson("/patients/{$this->patient->id}/odontogram")->assertUnauthorized();
    }

    #[Test]
    public function adding_a_procedure_rejects_unknown_codes_and_invalid_teeth(): void
    {
        $plan = TreatmentPlan::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'title' => 'Plan', 'status' => 'draft']);

        $this->actingAs($this->doctor)->postJson("/api/treatment-plans/{$plan->id}/procedures", ['tooth_number_fdi' => 36, 'procedure_code' => 'DXXXX'])->assertStatus(422);
        $this->actingAs($this->doctor)->postJson("/api/treatment-plans/{$plan->id}/procedures", ['tooth_number_fdi' => 19, 'procedure_code' => 'D2391'])->assertUnprocessable();
        $this->assertDatabaseMissing('procedure_codes', ['code' => 'DXXXX']);

        $this->actingAs($this->doctor)->postJson("/api/treatment-plans/{$plan->id}/procedures", ['tooth_number_fdi' => 36, 'procedure_code' => 'D2391'])->assertOk();
        $this->assertEquals(900, (float) $plan->fresh()->total_amount);
    }

    #[Test]
    public function completing_a_procedure_sets_condition_by_code_and_clears_needs_treatment(): void
    {
        $this->chart('36', 'root_canal', 'needs_treatment');
        $plan = TreatmentPlan::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'title' => 'Plan', 'status' => 'draft']);
        $phase = $plan->phases()->create(['sequence' => 1, 'name' => 'Phase 1']);

        $crown = $phase->procedures()->create(['tooth_number_fdi' => 36, 'procedure_code_id' => ProcedureCode::where('code', 'D2740')->value('id'), 'fee' => 4500, 'net_amount' => 4500, 'status' => 'planned']);
        $this->actingAs($this->doctor)->patchJson("/api/procedures/{$crown->id}/complete")->assertOk()->assertJsonPath('condition', 'crown');

        $this->assertDatabaseHas('patient_teeth', ['patient_id' => $this->patient->id, 'tooth_number' => '36', 'condition' => 'crown', 'status' => 'existing']);

        // Pulp capping must not turn the tooth into a crown (the old title match did).
        $this->chart('11', 'active_caries', 'needs_treatment');
        $cap = $phase->procedures()->create(['tooth_number_fdi' => 11, 'procedure_code_id' => ProcedureCode::where('code', 'D3110')->value('id'), 'fee' => 300, 'net_amount' => 300, 'status' => 'planned']);
        $this->actingAs($this->doctor)->patchJson("/api/procedures/{$cap->id}/complete")->assertOk()->assertJsonPath('condition', null);
        $this->assertDatabaseHas('patient_teeth', ['patient_id' => $this->patient->id, 'tooth_number' => '11', 'condition' => 'active_caries']);
    }

    #[Test]
    public function a_new_exam_carries_forward_the_previous_chart(): void
    {
        $this->exam->update(['examined_at' => now()->subMonth()]);
        $this->chart('46', 'missing', 'existing');
        $this->chart('16', 'crown', 'existing');

        $new = $this->actingAs($this->doctor)->postJson("/api/patients/{$this->patient->id}/examinations", ['type' => 'periodic'])->assertCreated();

        $chart = $this->actingAs($this->doctor)->getJson("/api/examinations/{$new->json('id')}/odontogram")->assertOk();
        $chart->assertJsonPath('46.condition', 'missing');
        $chart->assertJsonPath('16.condition', 'crown');
        $chart->assertJsonPath('16.status', 'existing');
    }

    #[Test]
    public function fdi_and_code_rules(): void
    {
        $this->assertTrue(OdontogramRules::isValidFdi('11'));
        $this->assertTrue(OdontogramRules::isValidFdi('85'));
        $this->assertFalse(OdontogramRules::isValidFdi('19'));
        $this->assertFalse(OdontogramRules::isValidFdi('56'));
        $this->assertSame('missing', OdontogramRules::conditionAfterProcedure('D7140'));
        $this->assertSame('implant', OdontogramRules::conditionAfterProcedure('D6010'));
        $this->assertNull(OdontogramRules::conditionAfterProcedure('D0150'));
    }
}

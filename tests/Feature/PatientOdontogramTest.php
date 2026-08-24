<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientTooth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientOdontogramTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_test_3d_odontogram_route_without_patient()
    {
        $response = $this->get('/test-3d-odontogram');

        $response->assertStatus(200);
        $this->assertDatabaseHas('patients', [
            'file_number' => 'DEMO-001',
        ]);
    }

    public function test_can_access_test_3d_odontogram_route_with_existing_patient()
    {
        $patient = Patient::create([
            'file_number' => 'PAT-100',
            'full_name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
        ]);

        $response = $this->get("/test-3d-odontogram/{$patient->id}");

        $response->assertStatus(200);
    }

    public function test_can_fetch_patient_teeth_conditions()
    {
        $patient = Patient::create([
            'file_number' => 'PAT-101',
            'full_name' => 'Jane Smith',
            'phone' => '1234567891',
        ]);

        PatientTooth::create([
            'patient_id' => $patient->id,
            'tooth_number' => '16',
            'condition' => 'active_caries',
        ]);

        $response = $this->getJson("/api/patients/{$patient->id}/teeth");

        $response->assertStatus(200);
        $response->assertJson([
            '16' => 'active_caries',
        ]);
    }

    public function test_can_update_patient_tooth_condition()
    {
        $patient = Patient::create([
            'file_number' => 'PAT-102',
            'full_name' => 'Robert Johnson',
            'phone' => '1234567892',
        ]);

        $response = $this->postJson("/api/patients/{$patient->id}/teeth", [
            'tooth_number' => '21',
            'condition' => 'crown',
            'notes' => 'Porcelain fused to metal crown',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'tooth_number' => '21',
            'condition' => 'crown',
        ]);

        $this->assertDatabaseHas('patient_teeth', [
            'patient_id' => $patient->id,
            'tooth_number' => '21',
            'condition' => 'crown',
        ]);
    }
}

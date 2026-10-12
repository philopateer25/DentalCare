<?php

namespace Tests\Feature;

use App\Filament\Resources\PatientResource\Widgets\PatientMedicalHistory360Widget;
use App\Http\Controllers\Api\PatientVoiceNoteController;
use App\Models\Branch;
use App\Models\CommunicationLog;
use App\Models\ExternalTreatment;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Models\VoiceNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalUpgradesAndVoiceNotesTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $userA;
    protected User $userB;
    protected Patient $patientA;
    protected Patient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'clinic_admin']);

        // Practice A
        $this->practiceA = Practice::create([
            'name' => 'Care Dental A',
            'currency' => 'EGP',
            'is_active' => true,
            'features' => ['3d_model', 'whatsapp'],
        ]);
        $branchA = Branch::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Branch A Main',
        ]);
        $this->userA = User::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $branchA->id,
            'name' => 'Dr. Kareem A',
            'email' => 'kareem@practice-a.test',
            'password' => bcrypt('secret123'),
            'role' => 'doctor',
        ]);
        $this->userA->assignRole('doctor');

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'file_number' => 'PAT-A-101',
            'first_name' => 'Mina',
            'last_name' => 'Fouad',
            'phone' => '01012345678',
            'whatsapp_number' => '01012345678',
            'gender' => 'male',
        ]);

        // Practice B
        $this->practiceB = Practice::create([
            'name' => 'Care Dental B',
            'currency' => 'EGP',
            'is_active' => true,
            'features' => ['3d_model', 'whatsapp'],
        ]);
        $branchB = Branch::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Branch B Main',
        ]);
        $this->userB = User::create([
            'practice_id' => $this->practiceB->id,
            'branch_id' => $branchB->id,
            'name' => 'Dr. Sara B',
            'email' => 'sara@practice-b.test',
            'password' => bcrypt('secret123'),
            'role' => 'doctor',
        ]);
        $this->userB->assignRole('doctor');

        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'file_number' => 'PAT-B-202',
            'first_name' => 'Layla',
            'last_name' => 'Nabil',
            'phone' => '01298765432',
            'whatsapp_number' => '01298765432',
            'gender' => 'female',
        ]);
    }

    public function test_phone_normalization_formats_egyptian_numbers_correctly(): void
    {
        $this->assertEquals('201012345678', PatientVoiceNoteController::normalizePhoneNumber('01012345678'));
        $this->assertEquals('201123456789', PatientVoiceNoteController::normalizePhoneNumber('+20 112 345 6789'));
        $this->assertEquals('201200000000', PatientVoiceNoteController::normalizePhoneNumber('00201200000000'));
        $this->assertEquals('201555555555', PatientVoiceNoteController::normalizePhoneNumber('015-5555-5555'));
    }

    public function test_cross_tenant_voice_note_upload_is_rejected_with_403(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson("/api/patients/{$this->patientB->id}/voice-notes", [
            'template' => 'extraction_care',
            'tooth_number_fdi' => 46,
        ]);

        $response->assertStatus(403);
    }

    public function test_voice_note_validation_rejects_missing_file_and_template(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson("/api/patients/{$this->patientA->id}/voice-notes", [
            'tooth_number_fdi' => 11,
        ]);

        $response->assertStatus(422);
    }

    public function test_voice_note_validation_rejects_oversized_files(): void
    {
        $this->actingAs($this->userA);

        $oversizedFile = UploadedFile::fake()->create('large_audio.wav', 12000); // 12MB > 10MB limit

        $response = $this->postJson("/api/patients/{$this->patientA->id}/voice-notes", [
            'audio' => $oversizedFile,
        ]);

        $response->assertStatus(422);
    }

    public function test_voice_note_creation_from_template_and_streaming(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson("/api/patients/{$this->patientA->id}/voice-notes", [
            'template' => 'extraction_care',
            'tooth_number_fdi' => 36,
            'duration_seconds' => 45,
        ]);

        $response->assertStatus(201);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertNotEmpty($json['share_url']);
        $this->assertNotEmpty($json['whatsapp_web_url']);
        $this->assertEquals('201012345678', $json['normalized_phone']);
        $this->assertStringContainsString('web.whatsapp.com/send?phone=201012345678', $json['whatsapp_web_url']);

        $voiceNoteId = $json['voice_note']['id'];
        $voiceNote = VoiceNote::find($voiceNoteId);
        $this->assertNotNull($voiceNote);
        $this->assertEquals(64, strlen($voiceNote->share_token));
        $this->assertEquals(36, $voiceNote->tooth_number_fdi);
        $this->assertEquals('link_prepared', $voiceNote->whatsapp_status);

        // Verify Communication Log audit trail
        $commLog = CommunicationLog::where('message_id', "VN-{$voiceNote->id}")->first();
        $this->assertNotNull($commLog);
        $this->assertEquals('link_prepared', $commLog->status);
        $this->assertEquals('whatsapp_voice_note', $commLog->type);

        // Verify Token Streaming
        $streamResponse = $this->get("/voice-notes/stream/{$voiceNote->share_token}");
        $streamResponse->assertStatus(200);
        $streamResponse->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_voice_note_token_revocation_and_expiration(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson("/api/patients/{$this->patientA->id}/voice-notes", [
            'template' => 'root_canal_care',
            'tooth_number_fdi' => 21,
        ]);

        $token = $response->json('voice_note.share_token');
        $voiceNoteId = $response->json('voice_note.id');
        $voiceNote = VoiceNote::find($voiceNoteId);

        // Valid initially
        $this->get("/voice-notes/stream/{$token}")->assertStatus(200);

        // Revoke token
        $revokeResponse = $this->postJson("/api/patients/{$this->patientA->id}/voice-notes/{$voiceNote->id}/revoke");
        $revokeResponse->assertStatus(200);

        $voiceNote->refresh();
        $this->assertTrue($voiceNote->is_revoked);
        $this->assertFalse($voiceNote->isValidForStreaming());

        // Stream must now return 404
        $this->get("/voice-notes/stream/{$token}")->assertStatus(404);

        // Test Expired token behavior
        $voiceNote->update([
            'is_revoked' => false,
            'token_expires_at' => now()->subDay(),
        ]);
        $this->assertFalse($voiceNote->isValidForStreaming());
        $this->get("/voice-notes/stream/{$token}")->assertStatus(404);
    }

    public function test_external_treatment_creation_sets_created_by_and_respects_tenancy(): void
    {
        $this->actingAs($this->userA);

        $widget = new PatientMedicalHistory360Widget();
        $widget->mount($this->patientA);

        $widget->extProcedureTitle = 'Zirconia Crown (Outside)';
        $widget->extToothNumber = '16';
        $widget->extPerformedYear = '2023';
        $widget->extClinicOrDoctor = 'Alexandria Center';
        $widget->extNotes = 'Adequate marginal adaptation';

        $widget->saveExternalTreatment();

        $treatment = ExternalTreatment::where('patient_id', $this->patientA->id)
            ->where('procedure_title', 'Zirconia Crown (Outside)')
            ->first();

        $this->assertNotNull($treatment);
        $this->assertEquals(16, $treatment->tooth_number_fdi);
        $this->assertEquals($this->userA->id, $treatment->created_by);
        $this->assertEquals($this->userA->name, $treatment->creator->name);

        // User B from Practice B should NOT be able to delete User A's patient external treatment
        $this->actingAs($this->userB);
        $widgetB = new PatientMedicalHistory360Widget();
        $widgetB->mount($this->patientA);

        try {
            $widgetB->deleteExternalTreatment($treatment->id);
            $this->fail('Expected 403 on cross-tenant deletion');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // Treatment still exists
        $this->assertDatabaseHas('external_treatments', ['id' => $treatment->id]);
    }

    public function test_medical_risk_assessment_distinguishes_positive_negative_and_unassessed(): void
    {
        $widget = new PatientMedicalHistory360Widget();

        // 1. Unassessed Patient (No MedicalHistory record)
        $widget->mount($this->patientA);
        $risksUnassessed = $widget->getMedicalRiskAssessment();

        $this->assertEquals('unknown', $risksUnassessed['diabetes']['level']);
        $this->assertEquals('Unassessed / Missing Data', $risksUnassessed['diabetes']['badge']);
        $this->assertEquals('unknown', $risksUnassessed['bleeding']['level']);
        $this->assertEquals('unknown', $risksUnassessed['bisphosphonates']['level']);
        $this->assertEquals('unknown', $risksUnassessed['allergies']['level']);

        // 2. High Risk / Positive Findings Patient
        $medPositive = MedicalHistory::create([
            'patient_id' => $this->patientA->id,
            'diabetic_status' => true,
            'diabetes_type' => 'type_2',
            'hba1c_level' => '8.9%',
            'bleeding_disorder' => true,
            'anticoagulant_type' => 'warfarin',
            'inr_level' => '3.2',
            'bisphosphonate_therapy' => true,
            'bisphosphonate_details' => 'IV Zometa 3 years',
            'penicillin_allergy' => true,
            'latex_allergy' => true,
            'requires_antibiotic_prophylaxis' => true,
        ]);

        $this->patientA->refresh();
        $widget->mount($this->patientA);
        $risksPositive = $widget->getMedicalRiskAssessment();

        $this->assertEquals('danger', $risksPositive['diabetes']['level']);
        $this->assertStringContainsString('8.9%', $risksPositive['diabetes']['badge']);

        $this->assertEquals('danger', $risksPositive['bleeding']['level']);
        $this->assertStringContainsString('WARFARIN', $risksPositive['bleeding']['badge']);

        $this->assertEquals('danger', $risksPositive['bisphosphonates']['level']);
        $this->assertStringContainsString('Bisphosphonates Active', $risksPositive['bisphosphonates']['badge']);

        $this->assertEquals('danger', $risksPositive['allergies']['level']);
        $this->assertStringContainsString('Penicillin', $risksPositive['allergies']['description']);

        $this->assertEquals('danger', $risksPositive['cardiac']['level']);

        // 3. Documented Negative / Cleared Patient
        $medPositive->update([
            'diabetic_status' => false,
            'bleeding_disorder' => false,
            'bisphosphonate_therapy' => false,
            'penicillin_allergy' => false,
            'latex_allergy' => false,
            'local_anesthetic_allergy' => false,
            'requires_antibiotic_prophylaxis' => false,
        ]);

        $this->patientA->refresh();
        $widget->mount($this->patientA);
        $risksNegative = $widget->getMedicalRiskAssessment();

        $this->assertEquals('safe', $risksNegative['diabetes']['level']);
        $this->assertEquals('Documented Negative', $risksNegative['diabetes']['badge']);

        $this->assertEquals('safe', $risksNegative['bleeding']['level']);
        $this->assertEquals('Documented Negative', $risksNegative['bleeding']['badge']);

        $this->assertEquals('safe', $risksNegative['bisphosphonates']['level']);
        $this->assertEquals('Documented Cleared', $risksNegative['bisphosphonates']['badge']);

        $this->assertEquals('safe', $risksNegative['allergies']['level']);
        $this->assertEquals('No Known Allergies', $risksNegative['allergies']['badge']);
    }
}

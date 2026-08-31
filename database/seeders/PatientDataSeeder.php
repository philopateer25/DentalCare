<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPhase;
use App\Models\TreatmentProcedure;
use App\Models\ProcedureCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PatientDataSeeder extends Seeder
{
    public function run(): void
    {
        $practice = Practice::first();
        if (!$practice) return;

        $doctor = User::where('role', 'dentist')->first();
        if (!$doctor) return;

        // 1. Create Patient A (Complex Case)
        $patientA = Patient::firstOrCreate(
            ['file_number' => 'DC-2023-001'],
            [
                'practice_id' => $practice->id,
                'first_name' => 'Mohamed',
                'last_name' => 'El-Sayed',
                'full_name' => 'Mohamed El-Sayed',
                'national_id' => '29001010123456',
                'gender' => 'male',
                'dob' => '1990-05-15',
                'phone' => '01011112222',
                'status' => 'active',
            ]
        );

        // Pre-existing odontogram conditions for Patient A
        $patientA->teeth()->updateOrCreate(['tooth_number' => '11'], ['condition' => 'composite_filled']);
        $patientA->teeth()->updateOrCreate(['tooth_number' => '46'], ['condition' => 'active_caries']);
        $patientA->teeth()->updateOrCreate(['tooth_number' => '47'], ['condition' => 'missing']);

        // Medical History
        $patientA->medicalHistory()->firstOrCreate([], [
            'diabetic_status' => true,
            'cardiac_history' => false,
            'blood_type' => 'O+',
        ]);

        // Treatment Plan for Patient A
        $planA = TreatmentPlan::firstOrCreate(
            ['patient_id' => $patientA->id, 'title' => 'Comprehensive Restoration & Extraction'],
            [
                'doctor_id' => $doctor->id,
                'status' => 'approved',
                'total_amount' => 0,
                'discount_amount' => 0,
                'net_amount' => 0,
            ]
        );

        if ($planA->phases()->count() == 0) {
            $phase1 = $planA->phases()->create(['name' => 'Phase 1 - Surgery & RCT', 'sequence' => 1]);
            
            $extractionCode = ProcedureCode::where('code', 'D7140')->first(); // Extraction
            $rctCode = ProcedureCode::where('code', 'D3330')->first(); // Molar RCT

            if ($extractionCode) {
                $phase1->procedures()->create([
                    'procedure_code_id' => $extractionCode->id,
                    'tooth_number_fdi' => 46,
                    'doctor_id' => $doctor->id,
                    'fee' => $extractionCode->standard_fee,
                    'discount' => 0,
                    'net_amount' => $extractionCode->standard_fee,
                    'status' => 'completed',
                ]);
            }

            if ($rctCode) {
                $phase1->procedures()->create([
                    'procedure_code_id' => $rctCode->id,
                    'tooth_number_fdi' => 36,
                    'doctor_id' => $doctor->id,
                    'fee' => $rctCode->standard_fee,
                    'discount' => 500, // Discount applied
                    'net_amount' => $rctCode->standard_fee - 500,
                    'status' => 'planned',
                ]);
            }
            
            $planA->recalculateTotals();
        }

        // 2. Create Patient B (Simple Checkup)
        $patientB = Patient::firstOrCreate(
            ['file_number' => 'DC-2023-002'],
            [
                'practice_id' => $practice->id,
                'first_name' => 'Salma',
                'last_name' => 'Ibrahim',
                'full_name' => 'Salma Ibrahim',
                'gender' => 'female',
                'dob' => '1995-10-20',
                'phone' => '01233334444',
                'status' => 'active',
            ]
        );

        $patientB->medicalHistory()->firstOrCreate([], [
            'penicillin_allergy' => true,
        ]);
    }
}

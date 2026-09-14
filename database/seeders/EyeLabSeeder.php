<?php

namespace Database\Seeders;

use App\Models\DentalLab;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EyeLabSeeder extends Seeder
{
    public ?Practice $targetPractice = null;

    public function run(): void
    {
        $practice = $this->targetPractice ?? Practice::where('type', 'ophthalmology')->first();

        if (!$practice) {
            return;
        }

        $doctor = User::where('practice_id', $practice->id)->first();
        $patient = Patient::where('practice_id', $practice->id)->first();

        if (!$doctor || !$patient) {
            return;
        }

        $lab = DentalLab::firstOrCreate(
            ['name' => 'ClearVision Prosthetics & Lenses'],
            ['practice_id' => $practice->id, 'lab_type' => 'Ophthalmic Lab', 'is_active' => true]
        );

        LabOrder::firstOrCreate(
            ['tracking_number' => 'EYE-LAB-001'],
            [
                'practice_id' => $practice->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $lab->id,
                'order_type' => 'Custom Scleral Lens',
                'material' => 'Gas Permeable Polymer',
                'status' => 'in_production',
                'sent_at' => Carbon::now()->subDays(2),
                'expected_delivery_at' => Carbon::now()->addDays(5),
                'cost' => 150.00,
                'patient_charge' => 500.00,
            ]
        );
    }
}

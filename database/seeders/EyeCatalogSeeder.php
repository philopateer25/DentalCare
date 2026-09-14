<?php

namespace Database\Seeders;

use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use Illuminate\Database\Seeder;

class EyeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Comprehensive Exams' => [
                ['code' => 'E0120', 'title' => 'Comprehensive Eye Exam', 'fee' => 400, 'duration' => 30],
                ['code' => 'E0130', 'title' => 'Refraction Testing', 'fee' => 150, 'duration' => 15],
            ],
            'Diagnostic Imaging' => [
                ['code' => 'E0210', 'title' => 'OCT (Optical Coherence Tomography)', 'fee' => 600, 'duration' => 20],
                ['code' => 'E0220', 'title' => 'Retinal Photography', 'fee' => 350, 'duration' => 15],
            ],
            'Surgical Procedures' => [
                ['code' => 'E3310', 'title' => 'LASIK Eye Surgery (Bilateral)', 'fee' => 15000, 'duration' => 60],
                ['code' => 'E3320', 'title' => 'Cataract Extraction with IOL', 'fee' => 12000, 'duration' => 90],
            ],
        ];

        foreach ($catalog as $categoryName => $procedures) {
            $category = ProcedureCategory::firstOrCreate(
                ['name' => $categoryName],
                [
                    'code' => 'E' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $categoryName), 0, 2)),
                    'description' => $categoryName
                ]
            );

            foreach ($procedures as $proc) {
                ProcedureCode::firstOrCreate(
                    ['code' => $proc['code']],
                    [
                        'category_id' => $category->id,
                        'title' => $proc['title'],
                        'standard_fee' => $proc['fee'],
                        'estimated_duration_minutes' => $proc['duration'],
                        'description' => 'Standard ' . $proc['title'],
                    ]
                );
            }
        }
    }
}

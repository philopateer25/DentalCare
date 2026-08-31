<?php

namespace Database\Seeders;

use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use Illuminate\Database\Seeder;

class DentalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Diagnostics & Preventive' => [
                ['code' => 'D0120', 'title' => 'Periodic Oral Evaluation', 'fee' => 300, 'duration' => 15],
                ['code' => 'D0210', 'title' => 'Intraoral - Complete Series of Radiographic Images', 'fee' => 500, 'duration' => 20],
                ['code' => 'D0220', 'title' => 'Intraoral - Periapical First Radiographic Image', 'fee' => 100, 'duration' => 10],
                ['code' => 'D1110', 'title' => 'Prophylaxis (Scaling & Polishing) - Adult', 'fee' => 600, 'duration' => 30],
                ['code' => 'D1206', 'title' => 'Topical Application of Fluoride Varnish', 'fee' => 250, 'duration' => 15],
            ],
            'Restorative (Fillings)' => [
                ['code' => 'D2330', 'title' => 'Composite Resin - One Surface, Anterior', 'fee' => 800, 'duration' => 30],
                ['code' => 'D2331', 'title' => 'Composite Resin - Two Surfaces, Anterior', 'fee' => 1000, 'duration' => 45],
                ['code' => 'D2391', 'title' => 'Composite Resin - One Surface, Posterior', 'fee' => 900, 'duration' => 30],
                ['code' => 'D2392', 'title' => 'Composite Resin - Two Surfaces, Posterior', 'fee' => 1200, 'duration' => 45],
                ['code' => 'D2740', 'title' => 'Crown - Porcelain/Ceramic', 'fee' => 4500, 'duration' => 60],
                ['code' => 'D2750', 'title' => 'Crown - Porcelain Fused to High Noble Metal', 'fee' => 3800, 'duration' => 60],
            ],
            'Endodontics (Root Canal)' => [
                ['code' => 'D3310', 'title' => 'Endodontic Therapy, Anterior Tooth', 'fee' => 2500, 'duration' => 60],
                ['code' => 'D3320', 'title' => 'Endodontic Therapy, Premolar Tooth', 'fee' => 3200, 'duration' => 90],
                ['code' => 'D3330', 'title' => 'Endodontic Therapy, Molar Tooth', 'fee' => 4500, 'duration' => 120],
            ],
            'Periodontics' => [
                ['code' => 'D4341', 'title' => 'Periodontal Scaling and Root Planing', 'fee' => 1500, 'duration' => 45],
                ['code' => 'D4910', 'title' => 'Periodontal Maintenance', 'fee' => 700, 'duration' => 30],
            ],
            'Prosthodontics (Dentures & Bridges)' => [
                ['code' => 'D5110', 'title' => 'Complete Denture - Maxillary', 'fee' => 8000, 'duration' => 60],
                ['code' => 'D5120', 'title' => 'Complete Denture - Mandibular', 'fee' => 8000, 'duration' => 60],
                ['code' => 'D6240', 'title' => 'Pontic - Porcelain Fused to High Noble Metal', 'fee' => 3500, 'duration' => 45],
            ],
            'Oral & Maxillofacial Surgery' => [
                ['code' => 'D7140', 'title' => 'Extraction, Erupted Tooth or Exposed Root', 'fee' => 700, 'duration' => 30],
                ['code' => 'D7210', 'title' => 'Surgical Extraction of Erupted Tooth', 'fee' => 1500, 'duration' => 45],
                ['code' => 'D7220', 'title' => 'Removal of Impacted Tooth - Soft Tissue', 'fee' => 2500, 'duration' => 60],
                ['code' => 'D7230', 'title' => 'Removal of Impacted Tooth - Partially Bony', 'fee' => 3500, 'duration' => 60],
                ['code' => 'D7240', 'title' => 'Removal of Impacted Tooth - Completely Bony', 'fee' => 4500, 'duration' => 90],
            ],
            'Implantology' => [
                ['code' => 'D6010', 'title' => 'Surgical Placement of Implant Body', 'fee' => 12000, 'duration' => 120],
                ['code' => 'D6058', 'title' => 'Abutment Supported Porcelain/Ceramic Crown', 'fee' => 6000, 'duration' => 60],
            ],
            'Orthodontics' => [
                ['code' => 'D8080', 'title' => 'Comprehensive Orthodontic Treatment', 'fee' => 25000, 'duration' => 60],
                ['code' => 'D8670', 'title' => 'Periodic Orthodontic Treatment Visit', 'fee' => 500, 'duration' => 20],
            ],
        ];

        foreach ($catalog as $categoryName => $procedures) {
            $category = ProcedureCategory::firstOrCreate(
                ['name' => $categoryName],
                [
                    'code' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $categoryName), 0, 3)),
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

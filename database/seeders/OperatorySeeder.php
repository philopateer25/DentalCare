<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Operatory;
use Illuminate\Database\Seeder;

class OperatorySeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first();

        if ($branch) {
            Operatory::firstOrCreate(
                ['name' => 'Room 1 - Hygiene', 'branch_id' => $branch->id],
                ['code' => 'R1', 'is_active' => true]
            );

            Operatory::firstOrCreate(
                ['name' => 'Room 2 - Surgery', 'branch_id' => $branch->id],
                ['code' => 'R2', 'is_active' => true]
            );
        }
    }
}

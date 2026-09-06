<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Patient;
use App\Models\PatientTooth;
use Illuminate\Support\Facades\DB;

class MigrateOdontogramData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odontogram:migrate-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy patient_teeth data to the new dental_examinations structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of legacy patient_teeth data...');

        $patients = Patient::whereHas('teeth')->get();
        $this->info("Found {$patients->count()} patients with legacy teeth data.");

        $count = 0;

        DB::transaction(function () use ($patients, &$count) {
            foreach ($patients as $patient) {
                // Create an initial baseline examination for the patient
                $examination = $patient->examinations()->create([
                    'type' => 'initial',
                    'notes' => 'Migrated from legacy patient_teeth table',
                    'examined_at' => now(), // Or use the oldest created_at from patient_teeth
                ]);

                $teeth = $patient->teeth()->get();

                foreach ($teeth as $tooth) {
                    // Create the finding
                    $finding = $examination->toothFindings()->create([
                        'tooth_number_fdi' => $tooth->tooth_number,
                        'finding_type' => $tooth->condition,
                        'notes' => $tooth->notes,
                    ]);

                    // Create surface findings if any
                    if (is_array($tooth->surfaces)) {
                        foreach ($tooth->surfaces as $surface => $condition) {
                            $finding->surfaceFindings()->create([
                                'surface' => $surface,
                                'finding_type' => $condition,
                            ]);
                        }
                    }
                }
                $count++;
            }
        });

        $this->info("Successfully migrated data for {$count} patients.");
        return Command::SUCCESS;
    }
}

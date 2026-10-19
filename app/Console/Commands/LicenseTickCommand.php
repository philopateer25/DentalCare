<?php

namespace App\Console\Commands;

use App\Services\TamperProofClock;
use Illuminate\Console\Command;

/**
 * Persists the tamper-proof clock state to encrypted file + DB every tick interval.
 *
 * Scheduled to run every 10 minutes by the Laravel scheduler.
 * Each tick writes the current accumulated elapsed time and OS clock reading,
 * enabling the clock to survive reboots and detect tampering.
 */
class LicenseTickCommand extends Command
{
    protected $signature = 'license:tick';

    protected $description = 'Persist the tamper-proof clock state (run by scheduler every 10 minutes)';

    public function handle(TamperProofClock $clock): int
    {
        if ($clock->isTampered()) {
            $this->error('Tamper detected: ' . $clock->getTamperReason());
            return Command::FAILURE;
        }

        $clock->tick();

        $elapsed = $clock->getElapsedSecondsSinceSync();
        $hours = round($elapsed / 3600, 1);

        $this->info("Clock tick persisted. Elapsed since last sync: {$hours}h");

        return Command::SUCCESS;
    }
}

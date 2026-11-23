<?php

namespace App\Console\Commands;

use App\Services\LicenseManager;
use Illuminate\Console\Command;

/**
 * Phase 6 — activate with the enrollment code from the central app (used by
 * the Windows installer; staff can also do it on the License page).
 */
class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {code : Enrollment code from the central app, e.g. DXA-XXXX-XXXX-XXXX}';

    protected $description = 'Activate this PC with an enrollment code from the central app';

    public function handle(LicenseManager $license): int
    {
        $result = $license->activate(trim((string) $this->argument('code')));

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}

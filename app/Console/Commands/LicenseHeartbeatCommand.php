<?php

namespace App\Console\Commands;

use App\Services\LicenseManager;
use Illuminate\Console\Command;

/**
 * Sends a heartbeat to the Central license server every N hours.
 *
 * If the server responds with 'active', the grace period clock resets.
 * If 'suspended' or 'revoked', the app locks out immediately.
 * If the server is unreachable, nothing changes — the grace clock keeps ticking.
 */
class LicenseHeartbeatCommand extends Command
{
    protected $signature = 'license:heartbeat';

    protected $description = 'Send a heartbeat to the central licensing server and update local state';

    public function handle(LicenseManager $licenseManager): int
    {
        $this->info('Starting license heartbeat...');

        $result = $licenseManager->sendHeartbeat();

        if ($result['success']) {
            $this->info('✅ ' . $result['message']);
            return Command::SUCCESS;
        }

        $this->warn('⚠️ ' . $result['message']);

        // If the license was revoked or suspended, this is a "handled" failure.
        $status = $result['status'] ?? null;
        if (in_array($status, [LicenseManager::STATUS_REVOKED, LicenseManager::STATUS_SUSPENDED], true)) {
            $this->error("License status: {$status}. Application will be locked.");
        }

        return Command::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseEnvelope;
use Illuminate\Console\Command;

/**
 * Generates an Ed25519 key pair for signing licenses.
 *
 * The SECRET key belongs only in the central app's secrets (never in this
 * repo or on a clinic PC). The PUBLIC key goes in config/license.php.
 */
class LicenseKeygenCommand extends Command
{
    protected $signature = 'license:keygen {--kid=k1 : Key id to label the pair with}';

    protected $description = 'Generate an Ed25519 key pair for signing license payloads';

    public function handle(): int
    {
        $pair = LicenseEnvelope::generateKeyPair();
        $kid = $this->option('kid');

        $this->line("Key id: {$kid}");
        $this->newLine();
        $this->info('PUBLIC key  → config/license.php public_keys (or LICENSE_PUBLIC_KEYS outside production):');
        $this->line("{$kid}:{$pair['public']}");
        $this->newLine();
        $this->warn('SECRET key  → central app secret store ONLY. Never commit it or put it on a clinic PC:');
        $this->line($pair['secret']);

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\LicenseManager;

class LicenseHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'license:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a heartbeat to the central licensing server and updates local state';

    /**
     * Execute the console command.
     */
    public function handle(LicenseManager $licenseManager)
    {
        $this->info('Starting license heartbeat...');
        
        $state = $licenseManager->getLicenseState();
        
        if (!isset($state['clinic_id'])) {
            $this->error('Clinic ID not found. Has this installation been enrolled?');
            return Command::FAILURE;
        }

        try {
            // Mocking the call to the Central API
            // In a real scenario, this would be an external URL loaded from config
            $response = Http::timeout(10)->post(url('/api/internal/license/heartbeat'), [
                'clinic_id' => $state['clinic_id'],
                'hardware_id' => 'HW-12345', // In reality, fetch from OS
                'version' => '1.0.0',
                'timestamp' => now()->toIso8601String()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $licenseManager->updateLicenseState([
                    'status' => $data['status'] ?? 'inactive',
                    'grace_period_expires_at' => now()->addDays(7)->toDateTimeString()
                ]);
                
                $this->info('Heartbeat successful. License state updated to: ' . ($data['status'] ?? 'inactive'));
                return Command::SUCCESS;
            } else {
                $this->warn('Central server responded with an error: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('Could not connect to central server: ' . $e->getMessage());
            // Here, we don't change the license state. The LicenseManager's getLicenseState() 
            // will automatically enforce the grace period if time runs out.
        }

        return Command::FAILURE;
    }
}

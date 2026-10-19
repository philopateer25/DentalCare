<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Generates a unique hardware fingerprint for this machine.
 *
 * The fingerprint is a SHA-256 hash of stable system identifiers, making it
 * unique to each physical/virtual machine. This prevents a license activated
 * on one machine from being cloned to another.
 *
 * On Linux : uses /etc/machine-id + primary MAC address.
 * On macOS : uses IOPlatformSerialNumber + primary MAC address.
 * On Windows: uses MachineGuid from registry + primary MAC address.
 */
class HardwareFingerprint
{
    /**
     * Generate the hardware fingerprint for this machine.
     *
     * @return string  A hex-encoded SHA-256 hash (64 characters).
     */
    public static function generate(): string
    {
        $components = [];

        // Component 1: Machine ID
        $components[] = self::getMachineId();

        // Component 2: Primary MAC address
        $components[] = self::getPrimaryMacAddress();

        // Component 3: Hostname (less unique but adds entropy)
        $components[] = gethostname() ?: 'unknown-host';

        $raw = implode('|', $components);

        return hash('sha256', $raw);
    }

    /**
     * Get a stable machine identifier from the OS.
     */
    private static function getMachineId(): string
    {
        // Linux: /etc/machine-id is a stable UUID generated at install.
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/etc/machine-id')) {
            $id = trim(file_get_contents('/etc/machine-id'));
            if (!empty($id)) {
                return $id;
            }
        }

        // macOS: IOPlatformSerialNumber via ioreg.
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = self::shellExec('ioreg -rd1 -c IOPlatformExpertDevice | awk -F\'"\'  \'/IOPlatformSerialNumber/{print $4}\'');
            if (!empty($output)) {
                return $output;
            }
        }

        // Windows: MachineGuid from the registry.
        if (PHP_OS_FAMILY === 'Windows') {
            $output = self::shellExec('reg query "HKLM\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid 2>nul');
            if (preg_match('/MachineGuid\s+REG_SZ\s+(.+)/i', $output, $m)) {
                return trim($m[1]);
            }
        }

        // Fallback: php_uname() provides kernel + hostname info.
        return php_uname();
    }

    /**
     * Get the primary network interface's MAC address.
     */
    private static function getPrimaryMacAddress(): string
    {
        // Linux: read from /sys/class/net
        if (PHP_OS_FAMILY === 'Linux') {
            $interfaces = @scandir('/sys/class/net') ?: [];
            foreach ($interfaces as $iface) {
                if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                    continue;
                }
                $macPath = "/sys/class/net/{$iface}/address";
                if (is_readable($macPath)) {
                    $mac = trim(file_get_contents($macPath));
                    if ($mac && $mac !== '00:00:00:00:00:00') {
                        return $mac;
                    }
                }
            }
        }

        // macOS: ifconfig en0
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = self::shellExec('ifconfig en0 2>/dev/null | awk \'/ether/{print $2}\'');
            if (!empty($output)) {
                return $output;
            }
        }

        // Windows: getmac
        if (PHP_OS_FAMILY === 'Windows') {
            $output = self::shellExec('getmac /fo csv /nh 2>nul');
            if (preg_match('/"([0-9A-F\-]+)"/i', $output, $m)) {
                return $m[1];
            }
        }

        return 'no-mac-found';
    }

    /**
     * Run a shell command and return trimmed output.
     */
    private static function shellExec(string $command): string
    {
        try {
            $output = @shell_exec($command);
            return $output ? trim($output) : '';
        } catch (\Throwable $e) {
            Log::debug('HardwareFingerprint: shell command failed.', [
                'command' => $command,
                'error'   => $e->getMessage(),
            ]);
            return '';
        }
    }
}

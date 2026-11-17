<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupEncryptor
{
    protected const MAGIC_HEADER = "DCARE_ENC\x01";
    protected const CHUNK_SIZE = 65536; // 64 KB
    protected const SALT = 'dentalcare-backup-key-salt-v1';

    /**
     * Encrypt a file using streaming AES-256-CBC with HKDF key derivation
     * and HMAC-SHA256 authenticated encryption (Encrypt-then-MAC).
     *
     * @param string $sourcePath Path to plaintext file
     * @param string $destinationPath Path to output encrypted file
     * @param string $masterKey Master secret / passphrase
     * @throws RuntimeException
     */
    public function encryptFile(string $sourcePath, string $destinationPath, string $masterKey): void
    {
        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Source file for encryption does not exist: {$sourcePath}");
        }

        $source = fopen($sourcePath, 'rb');
        if (!$source) {
            throw new RuntimeException("Could not open source file for reading: {$sourcePath}");
        }

        // Derive distinct encryption and MAC keys using HKDF-SHA256
        $encKey = hash_hkdf('sha256', $masterKey, 32, 'encryption', self::SALT);
        $macKey = hash_hkdf('sha256', $masterKey, 32, 'authentication', self::SALT);

        $iv = random_bytes(16);

        // Open destination file
        $dest = fopen($destinationPath, 'wb+');
        if (!$dest) {
            fclose($source);
            throw new RuntimeException("Could not open destination file for writing: {$destinationPath}");
        }

        // Write header and IV
        fwrite($dest, self::MAGIC_HEADER);
        fwrite($dest, $iv);

        // Reserve 32 bytes for the HMAC-SHA256
        $hmacPlaceholder = str_repeat("\x00", 32);
        fwrite($dest, $hmacPlaceholder);

        // Initialize streaming HMAC context
        $hmacContext = hash_init('sha256', HASH_HMAC, $macKey);
        hash_update($hmacContext, self::MAGIC_HEADER);
        hash_update($hmacContext, $iv);

        // Stream encrypt in 64KB chunks
        $currentIv = $iv;
        $fileSize = filesize($sourcePath);
        $bytesRead = 0;

        while (!feof($source)) {
            $chunk = fread($source, self::CHUNK_SIZE);
            if ($chunk === false || strlen($chunk) === 0) {
                break;
            }

            $bytesRead += strlen($chunk);
            $isLast = ($bytesRead >= $fileSize);

            if ($isLast) {
                // Apply PKCS#7 padding on final block
                $pad = 16 - (strlen($chunk) % 16);
                $chunk .= str_repeat(chr($pad), $pad);
            }

            $cipherChunk = openssl_encrypt(
                $chunk,
                'aes-256-cbc',
                $encKey,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
                $currentIv
            );

            if ($cipherChunk === false) {
                fclose($source);
                fclose($dest);
                @unlink($destinationPath);
                throw new RuntimeException("OpenSSL encryption error: " . openssl_error_string());
            }

            // Update HMAC and write to file
            hash_update($hmacContext, $cipherChunk);
            fwrite($dest, $cipherChunk);

            // In CBC mode, next IV is the last 16 bytes of this chunk's ciphertext
            $currentIv = substr($cipherChunk, -16);
        }

        fclose($source);

        // Finalize HMAC
        $hmac = hash_final($hmacContext, true);

        // Seek back to overwrite HMAC placeholder (after 10 bytes magic + 16 bytes IV = 26 bytes offset)
        fseek($dest, strlen(self::MAGIC_HEADER) + 16);
        fwrite($dest, $hmac);

        fclose($dest);
    }

    /**
     * Verify and decrypt an encrypted backup file back to its original plaintext form.
     *
     * @param string $sourcePath Path to encrypted file (.enc)
     * @param string $destinationPath Path to output decrypted file (.zip)
     * @param string $masterKey Master secret / passphrase
     * @throws RuntimeException
     */
    public function decryptFile(string $sourcePath, string $destinationPath, string $masterKey): void
    {
        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Encrypted backup file does not exist: {$sourcePath}");
        }

        $source = fopen($sourcePath, 'rb');
        if (!$source) {
            throw new RuntimeException("Could not open encrypted file for reading: {$sourcePath}");
        }

        // Verify magic header
        $magic = fread($source, strlen(self::MAGIC_HEADER));
        if ($magic !== self::MAGIC_HEADER) {
            fclose($source);
            throw new RuntimeException("Invalid backup file header. This is not a valid encrypted DentalCare backup.");
        }

        // Read IV and stored HMAC
        $iv = fread($source, 16);
        $storedHmac = fread($source, 32);

        if (strlen($iv) !== 16 || strlen($storedHmac) !== 32) {
            fclose($source);
            throw new RuntimeException("Encrypted backup header is truncated or corrupt.");
        }

        // Derive keys
        $encKey = hash_hkdf('sha256', $masterKey, 32, 'encryption', self::SALT);
        $macKey = hash_hkdf('sha256', $masterKey, 32, 'authentication', self::SALT);

        // Pass 1: Verify HMAC over the entire ciphertext to ensure authenticity before decrypting
        $hmacContext = hash_init('sha256', HASH_HMAC, $macKey);
        hash_update($hmacContext, self::MAGIC_HEADER);
        hash_update($hmacContext, $iv);

        $ciphertextOffset = ftell($source);

        while (!feof($source)) {
            $buffer = fread($source, self::CHUNK_SIZE);
            if ($buffer !== false && strlen($buffer) > 0) {
                hash_update($hmacContext, $buffer);
            }
        }

        $computedHmac = hash_final($hmacContext, true);

        if (!hash_equals($storedHmac, $computedHmac)) {
            fclose($source);
            throw new RuntimeException("Authentication failed: Invalid encryption key or backup has been tampered with.");
        }

        // Pass 2: Decrypt verified ciphertext
        fseek($source, $ciphertextOffset);
        $dest = fopen($destinationPath, 'wb');
        if (!$dest) {
            fclose($source);
            throw new RuntimeException("Could not open destination file for decrypted output: {$destinationPath}");
        }

        $currentIv = $iv;
        $fileSize = filesize($sourcePath);

        while (!feof($source)) {
            $cipherChunk = fread($source, self::CHUNK_SIZE);
            if ($cipherChunk === false || strlen($cipherChunk) === 0) {
                break;
            }

            $plainChunk = openssl_decrypt(
                $cipherChunk,
                'aes-256-cbc',
                $encKey,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
                $currentIv
            );

            if ($plainChunk === false) {
                fclose($source);
                fclose($dest);
                @unlink($destinationPath);
                throw new RuntimeException("OpenSSL decryption failure: " . openssl_error_string());
            }

            $currentIv = substr($cipherChunk, -16);
            $isLast = (ftell($source) >= $fileSize);

            if ($isLast) {
                // Strip PKCS#7 padding
                $pad = ord(substr($plainChunk, -1));
                if ($pad > 0 && $pad <= 16) {
                    $plainChunk = substr($plainChunk, 0, -$pad);
                }
            }

            fwrite($dest, $plainChunk);
        }

        fclose($source);
        fclose($dest);
    }

    /**
     * Test whether a file is an encrypted DentalCare backup.
     */
    public function isEncrypted(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) < strlen(self::MAGIC_HEADER)) {
            return false;
        }

        $fp = fopen($filePath, 'rb');
        $magic = fread($fp, strlen(self::MAGIC_HEADER));
        fclose($fp);

        return ($magic === self::MAGIC_HEADER);
    }
}


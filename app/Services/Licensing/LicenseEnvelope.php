<?php

namespace App\Services\Licensing;

/**
 * Signed license envelope exchanged with the DOXamigo central app.
 *
 * Wire format (JSON):
 *   {
 *     "kid":       "k1",                       // which public key signed it
 *     "payload":   "<base64url(JSON payload)>",
 *     "signature": "<base64url(Ed25519 signature over the payload string)>"
 *   }
 *
 * The signature covers the base64url payload string exactly as sent, so the
 * central app never has to agree with PHP on JSON key order or spacing.
 * See docs/licensing-protocol.md.
 */
final class LicenseEnvelope
{
    /**
     * Verify an envelope and return the decoded payload, or null when the
     * envelope is malformed, signed by an unknown key, or the signature fails.
     *
     * @param  array<string, string>  $publicKeys  kid => base64 Ed25519 public key
     */
    public static function verify(mixed $envelope, array $publicKeys): ?array
    {
        if (! is_array($envelope)) {
            return null;
        }

        $payloadB64 = $envelope['payload'] ?? null;
        $signatureB64 = $envelope['signature'] ?? null;
        $kid = $envelope['kid'] ?? 'k1';

        if (! is_string($payloadB64) || ! is_string($signatureB64) || ! is_string($kid)) {
            return null;
        }

        $publicKey = isset($publicKeys[$kid]) ? self::decode($publicKeys[$kid]) : null;
        $signature = self::decode($signatureB64);

        if ($publicKey === null || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            if (! sodium_crypto_sign_verify_detached($signature, $payloadB64, $publicKey)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $json = self::decode($payloadB64);

        if ($json === null) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Sign a payload. Used by tests and local tooling; production signing
     * happens only in the central app, which holds the private key.
     *
     * @param  string  $secretKeyB64  base64 Ed25519 secret key (64 bytes)
     */
    public static function sign(array $payload, string $secretKeyB64, string $kid = 'k1'): array
    {
        $payloadB64 = self::encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = sodium_crypto_sign_detached($payloadB64, self::decode($secretKeyB64) ?? '');

        return [
            'kid' => $kid,
            'payload' => $payloadB64,
            'signature' => self::encode($signature),
        ];
    }

    /**
     * Generate a new Ed25519 key pair (base64).
     *
     * @return array{public: string, secret: string}
     */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        ];
    }

    /**
     * Parse "k1:BASE64,k2:BASE64" into ['k1' => 'BASE64', ...].
     *
     * @return array<string, string>
     */
    public static function parseKeyList(?string $list): array
    {
        $keys = [];

        foreach (explode(',', (string) $list) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || ! str_contains($entry, ':')) {
                continue;
            }
            [$kid, $key] = explode(':', $entry, 2);
            $keys[trim($kid)] = trim($key);
        }

        return $keys;
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** Decode base64 or base64url (padding optional). */
    public static function decode(string $value): ?string
    {
        $value = strtr(trim($value), '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }
}

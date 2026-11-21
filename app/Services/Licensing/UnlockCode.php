<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Short emergency unlock codes that support can read to a clinic over the
 * phone when its internet has been down past the offline grace period.
 *
 * Format: XXXX-XXXX-XXXX-XXXX (16 base32 chars = 10 bytes)
 *   bytes 0-3 : expiry, in whole hours since the Unix epoch (big-endian)
 *   bytes 4-9 : first 6 bytes of HMAC-SHA256(secret, "doxamigo-unlock|{clinic_id}|{hours}")
 *
 * The secret is the per-clinic `unlock_secret` delivered inside the signed
 * license payload, so a code only works for the clinic it was made for.
 * See docs/licensing-protocol.md.
 */
final class UnlockCode
{
    /** A code can never extend offline use by more than this many hours. */
    public const MAX_HOURS = 168;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generate(string $secretB64, string $clinicId, CarbonInterface $validUntil): string
    {
        $hours = (int) ceil($validUntil->getTimestamp() / 3600);
        $bytes = pack('N', $hours).self::mac($secretB64, $clinicId, $hours);

        return implode('-', str_split(self::base32Encode($bytes), 4));
    }

    /**
     * @return CarbonImmutable|null when the unlock expires, or null if the code is invalid/expired
     */
    public static function verify(string $code, string $secretB64, string $clinicId, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());

        // Accept lower case, spaces/dashes and the usual look-alikes read over the phone.
        $normalized = strtr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)), ['0' => 'O', '1' => 'I', '8' => 'B']);
        $bytes = self::base32Decode($normalized);

        if ($bytes === null || strlen($bytes) !== 10) {
            return null;
        }

        $hours = unpack('N', substr($bytes, 0, 4))[1];

        if (! hash_equals(self::mac($secretB64, $clinicId, $hours), substr($bytes, 4))) {
            return null;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp($hours * 3600, $now->getTimezone());

        if ($expiresAt->lessThanOrEqualTo($now)) {
            return null;
        }

        // One extra hour of slack for the rounding up in generate().
        if ($expiresAt->greaterThan($now->addHours(self::MAX_HOURS + 1))) {
            return null;
        }

        return $expiresAt;
    }

    private static function mac(string $secretB64, string $clinicId, int $hours): string
    {
        $secret = LicenseEnvelope::decode($secretB64) ?? '';

        return substr(hash_hmac('sha256', "doxamigo-unlock|{$clinicId}|{$hours}", $secret, true), 0, 6);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $bits = '';
        foreach (str_split($text) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                return null;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}

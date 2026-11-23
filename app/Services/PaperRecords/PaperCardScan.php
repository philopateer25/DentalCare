<?php

namespace App\Services\PaperRecords;

use App\Models\Patient;
use App\Models\PatientFile;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 4 — photograph a patient's paper card with the receptionist's phone.
 *
 * The PC shows a QR code / short link with a random one-time token (10 min).
 * The phone (on the clinic Wi-Fi, not logged in) opens it, takes photos, and
 * they are saved to that patient's files on the private disk. The token works
 * for one upload only.
 */
class PaperCardScan
{
    public const TTL_MINUTES = 10;

    public const MAX_PHOTOS = 10;

    public const FILE_TYPE = 'paper_record';

    public const DISK = 'local';

    /** @return array{token: string, url: ?string, path: string, expires_at: Carbon} */
    public function start(Patient $patient, int $userId): array
    {
        $token = Str::random(32);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put($this->key($token), [
            'patient_id' => $patient->id,
            'practice_id' => $patient->practice_id,
            'user_id' => $userId,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        $path = route('paper-card.show', ['token' => $token], absolute: false);
        $base = $this->lanBaseUrl();

        return [
            'token' => $token,
            'url' => $base === null ? null : rtrim($base, '/').$path,
            'path' => $path,
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array{patient_id: int, practice_id: int, user_id: int, expires_at: string}|null */
    public function find(string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }

        $session = Cache::get($this->key($token));

        return is_array($session) ? $session : null;
    }

    /**
     * Save the photos to the patient's files and use up the token.
     *
     * @param  list<UploadedFile>  $photos
     */
    public function store(string $token, array $photos): ?int
    {
        // pull = read and delete: a second upload with the same link fails.
        $session = Cache::pull($this->key($token));
        if (! is_array($session)) {
            return null;
        }

        $patient = Patient::query()
            ->where('practice_id', $session['practice_id'])
            ->findOrFail($session['patient_id']);

        $date = now()->timezone(config('app.timezone'))->format('d M Y');
        $dir = "patient-files/{$patient->practice_id}/{$patient->id}";
        $saved = [];

        try {
            DB::transaction(function () use ($photos, $patient, $session, $date, $dir, &$saved) {
                foreach (array_values($photos) as $i => $photo) {
                    $ext = strtolower($photo->guessExtension() ?: $photo->getClientOriginalExtension() ?: 'jpg');
                    $path = $photo->storeAs($dir, 'paper-card-'.Str::uuid().'.'.$ext, self::DISK);
                    $saved[] = $path;

                    PatientFile::create([
                        'patient_id' => $patient->id,
                        'doctor_id' => $session['user_id'],
                        'type' => self::FILE_TYPE,
                        'title' => __('Paper card').' – '.$date.(count($photos) > 1 ? ' ('.($i + 1).')' : ''),
                        'file_path' => $path,
                        'notes' => __('Photographed with a phone.'),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete(array_filter($saved));
            throw $e;
        }

        return count($saved);
    }

    /**
     * The address a phone on the clinic network can open. CLINIC_LAN_URL wins;
     * otherwise the address the PC itself is using, unless that's "localhost".
     */
    public function lanBaseUrl(): ?string
    {
        $configured = trim((string) config('app.lan_url'));
        if ($configured !== '') {
            return $configured;
        }

        $request = request();
        $host = $request?->getHost();

        if (! $host || in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($host, '.localhost')) {
            return null;
        }

        return $request->getSchemeAndHttpHost();
    }

    /** SVG QR code, when bacon/bacon-qr-code is installed; otherwise null (the link is shown instead). */
    public static function qrSvg(string $text): ?string
    {
        if (! class_exists(Writer::class)) {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(240, 2),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($text);
    }

    private function key(string $token): string
    {
        return 'paper-card-scan:'.hash('sha256', $token);
    }
}

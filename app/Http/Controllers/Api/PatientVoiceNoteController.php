<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Models\Patient;
use App\Models\VoiceNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PatientVoiceNoteController extends Controller
{
    /**
     * Format phone number to international E.164 without plus sign (e.g., 201xxxxxxxxx for Egypt).
     */
    public static function normalizePhoneNumber(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        // Strip non-digits
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        // Remove international leading 00
        if (str_starts_with($cleaned, '00')) {
            $cleaned = substr($cleaned, 2);
        }

        // Egyptian local mobile numbers: 010, 011, 012, 015 (11 digits starting with 01)
        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            $cleaned = '2' . $cleaned; // Becomes 201xxxxxxxxx
        } elseif (str_starts_with($cleaned, '1') && strlen($cleaned) === 10) {
            $cleaned = '20' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Store a recorded or templated voice note.
     */
    public function store(Request $request, Patient $patient)
    {
        $user = auth()->user();
        $userPracticeId = $user?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;

        if ($userPracticeId && $patient->practice_id !== $userPracticeId) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $validated = $request->validate([
            'audio' => 'nullable|file|max:10240',
            'template' => 'nullable|string|in:extraction_care,implant_care,root_canal_care,composite_care',
            'title' => 'nullable|string|max:255',
            'tooth_number_fdi' => 'nullable|integer',
            'treatment_procedure_id' => 'nullable|exists:treatment_procedures,id',
            'duration_seconds' => 'nullable|integer|max:300',
        ]);

        if (!$request->hasFile('audio') && empty($validated['template'])) {
            return response()->json([
                'message' => 'Either an audio file upload or a template key is required.',
            ], 422);
        }

        $token = Str::random(64);
        $practiceId = $patient->practice_id ?? 1;
        $relativeDir = "voice_notes/{$practiceId}/{$patient->id}";
        $storageDir = storage_path("app/{$relativeDir}");

        if (!File::exists($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $extension = $file->getClientOriginalExtension() ?: 'webm';
            $mimeType = $file->getClientMimeType() ?: 'audio/webm';
            $filename = "{$token}.{$extension}";
            $targetPath = "{$storageDir}/{$filename}";
            $file->move($storageDir, $filename);
            $fileSize = File::size($targetPath);
            $filePath = "{$relativeDir}/{$filename}";
        } else {
            // Prerecorded template
            $templateKey = $validated['template'];
            $sourceWav = public_path("audio/templates/{$templateKey}.wav");
            if (!File::exists($sourceWav)) {
                return response()->json(['message' => 'Audio template file not found on server.'], 404);
            }
            $filename = "{$token}.wav";
            $targetPath = "{$storageDir}/{$filename}";
            File::copy($sourceWav, $targetPath);
            $mimeType = 'audio/wav';
            $fileSize = File::size($targetPath);
            $filePath = "{$relativeDir}/{$filename}";
        }

        $defaultTitles = [
            'extraction_care' => 'Post-Extraction Care Instructions',
            'implant_care' => 'Implant Surgery Post-Op Care',
            'root_canal_care' => 'Root Canal Therapy Aftercare',
            'composite_care' => 'Composite Filling Aftercare',
        ];

        $template = $validated['template'] ?? null;
        $title = $validated['title'] 
            ?? ($template ? ($defaultTitles[$template] ?? 'Post-Op Instructions') : 'Post-Op Care Audio');

        if (!empty($validated['tooth_number_fdi'])) {
            $title .= " (Tooth #{$validated['tooth_number_fdi']})";
        }

        $voiceNote = VoiceNote::create([
            'practice_id' => $practiceId,
            'patient_id' => $patient->id,
            'user_id' => $user?->id,
            'tooth_number_fdi' => $validated['tooth_number_fdi'] ?? null,
            'treatment_procedure_id' => $validated['treatment_procedure_id'] ?? null,
            'title' => $title,
            'file_path' => $filePath,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'share_token' => $token,
            'token_expires_at' => now()->addDays(14),
            'is_revoked' => false,
            'whatsapp_status' => 'link_prepared',
        ]);

        // Generate public share listening URL
        $shareUrl = url("/voice-notes/stream/{$token}");

        // Build WhatsApp deep link with Egyptian formatted phone
        $rawPhone = $patient->whatsapp_number ?: $patient->phone;
        $normalizedPhone = self::normalizePhoneNumber($rawPhone);
        $doctorName = $user?->name ?: 'Dr. Rafik';

        $toothInfo = !empty($validated['tooth_number_fdi']) ? " for Tooth #{$validated['tooth_number_fdi']}" : "";
        $messageText = "Hello {$patient->first_name}, this is {$doctorName} from DentalCare. Here are your post-op care audio instructions{$toothInfo}: {$shareUrl} . Wishing you a speedy recovery!";

        $webUrl = $normalizedPhone
            ? "https://web.whatsapp.com/send?phone={$normalizedPhone}&text=" . urlencode($messageText)
            : '';

        $mobileUrl = $normalizedPhone
            ? "https://wa.me/{$normalizedPhone}?text=" . urlencode($messageText)
            : '';

        // Audit log in communication_logs as link_prepared
        CommunicationLog::create([
            'practice_id' => $practiceId,
            'patient_id' => $patient->id,
            'direction' => 'outbound',
            'type' => 'whatsapp_voice_note',
            'message' => $messageText,
            'status' => 'link_prepared',
            'message_id' => "VN-{$voiceNote->id}",
        ]);

        return response()->json([
            'success' => true,
            'voice_note' => $voiceNote,
            'share_url' => $shareUrl,
            'download_url' => url("/voice-notes/download/{$token}"),
            'whatsapp_web_url' => $webUrl,
            'whatsapp_mobile_url' => $mobileUrl,
            'normalized_phone' => $normalizedPhone,
            'message_text' => $messageText,
        ], 201);
    }

    /**
     * Stream audio securely using unguessable share token.
     */
    public function stream(string $shareToken)
    {
        $voiceNote = VoiceNote::where('share_token', $shareToken)->first();

        if (!$voiceNote || !$voiceNote->isValidForStreaming()) {
            abort(404, 'Audio link is invalid, expired, or revoked.');
        }

        $fullPath = storage_path("app/{$voiceNote->file_path}");

        if (!File::exists($fullPath)) {
            abort(404, 'Audio recording not found.');
        }

        return response()->file($fullPath, [
            'Content-Type' => $voiceNote->mime_type,
            'Content-Disposition' => 'inline; filename="post_op_instructions.wav"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Download audio file securely.
     */
    public function download(string $shareToken)
    {
        $voiceNote = VoiceNote::where('share_token', $shareToken)->first();

        if (!$voiceNote || !$voiceNote->isValidForStreaming()) {
            abort(404, 'Audio link is invalid, expired, or revoked.');
        }

        $fullPath = storage_path("app/{$voiceNote->file_path}");

        if (!File::exists($fullPath)) {
            abort(404, 'Audio recording not found.');
        }

        return response()->download($fullPath, "post_op_care_patient_{$voiceNote->patient_id}.wav");
    }

    /**
     * Authenticated clinic streaming.
     */
    public function staffStream(Patient $patient, VoiceNote $voiceNote)
    {
        $userPracticeId = auth()->user()?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;
        if ($userPracticeId && $patient->practice_id !== $userPracticeId) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        if ($voiceNote->patient_id !== $patient->id) {
            abort(404);
        }

        $fullPath = storage_path("app/{$voiceNote->file_path}");
        if (!File::exists($fullPath)) {
            abort(404, 'Audio file missing.');
        }

        return response()->file($fullPath, [
            'Content-Type' => $voiceNote->mime_type,
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Revoke share token.
     */
    public function revoke(Patient $patient, VoiceNote $voiceNote)
    {
        $userPracticeId = auth()->user()?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;
        if ($userPracticeId && $patient->practice_id !== $userPracticeId) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $voiceNote->update(['is_revoked' => true]);

        return response()->json(['success' => true, 'message' => 'Link revoked successfully.']);
    }

    /**
     * List voice notes for patient.
     */
    public function index(Patient $patient)
    {
        $userPracticeId = auth()->user()?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;
        if ($userPracticeId && $patient->practice_id !== $userPracticeId) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $notes = $patient->voiceNotes()->with('user:id,name')->latest()->get();

        return response()->json($notes);
    }
}

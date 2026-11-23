<?php

namespace App\Http\Controllers;

use App\Exceptions\ReadOnlyModeException;
use App\Models\Patient;
use App\Services\PaperRecords\PaperCardScan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The phone side of "Scan paper card": no login, the one-time token is the key.
 */
class PaperCardScanController extends Controller
{
    public function __construct(private readonly PaperCardScan $scan) {}

    public function show(string $token): View
    {
        $session = $this->scan->find($token);

        if ($session === null) {
            return view('paper-card.message', ['ok' => false, 'message' => __('This link has expired or was already used. Press "Scan paper card" on the computer again.')]);
        }

        $patient = Patient::where('practice_id', $session['practice_id'])->find($session['patient_id']);

        return view('paper-card.upload', [
            'token' => $token,
            'firstName' => $patient?->first_name ?? '',
            'expiresAt' => Carbon::parse($session['expires_at']),
            'max' => PaperCardScan::MAX_PHOTOS,
        ]);
    }

    public function store(Request $request, string $token): View
    {
        if ($this->scan->find($token) === null) {
            return view('paper-card.message', ['ok' => false, 'message' => __('This link has expired or was already used. Press "Scan paper card" on the computer again.')]);
        }

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.PaperCardScan::MAX_PHOTOS],
            'photos.*' => ['file', 'image', 'max:12288'],
        ], [
            'photos.required' => __('Take or choose at least one photo.'),
            'photos.*.image' => __('Only photos can be sent.'),
            'photos.*.max' => __('A photo is larger than 12 MB.'),
        ]);

        try {
            $count = $this->scan->store($token, $request->file('photos'));
        } catch (ReadOnlyModeException) {
            return view('paper-card.message', ['ok' => false, 'message' => __('The clinic system is read-only right now, so photos cannot be saved.')]);
        }

        if ($count === null) {
            return view('paper-card.message', ['ok' => false, 'message' => __('This link has expired or was already used. Press "Scan paper card" on the computer again.')]);
        }

        return view('paper-card.message', ['ok' => true, 'message' => trans_choice(':count photo saved to the patient file.|:count photos saved to the patient file.', $count, ['count' => $count])]);
    }
}

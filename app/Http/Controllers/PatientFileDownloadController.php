<?php

namespace App\Http\Controllers;

use App\Models\PatientFile;
use App\Services\PaperRecords\PaperCardScan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Patient files behind login and a clinic check. Paper-card photos live on the
 * private disk; older uploads on the public disk are still served from there.
 */
class PatientFileDownloadController extends Controller
{
    public function __invoke(PatientFile $patientFile): Response
    {
        $patient = $patientFile->patient;

        abort_unless($patient && (int) $patient->practice_id === (int) auth()->user()->practice_id, 403, 'Unauthorized cross-tenant access.');

        $private = Storage::disk(PaperCardScan::DISK);
        if ($private->exists($patientFile->file_path)) {
            return $private->response($patientFile->file_path, basename($patientFile->file_path));
        }

        return redirect(asset('storage/'.$patientFile->file_path));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Licensing\ClinicDataExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Export all data" — always available, even when the license is locked,
 * so a clinic can never be cut off from its own records.
 */
class LicenseExportController extends Controller
{
    public function __invoke(Request $request, ClinicDataExporter $exporter): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user && $user->hasAnyRole(['super_admin', 'clinic_admin', 'developer']), 403, 'Only clinic administrators can export data.');
        abort_unless($user->practice_id, 403, 'Your account is not linked to a clinic.');

        @set_time_limit(600);

        $path = $exporter->export((int) $user->practice_id);
        $name = 'doxamigo-export-'.now()->format('Y-m-d-His').'.zip';

        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}

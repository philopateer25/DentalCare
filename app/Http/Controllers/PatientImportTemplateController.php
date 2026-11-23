<?php

namespace App\Http\Controllers;

use App\Services\PaperRecords\PatientImport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Excel template for the patient + opening-balance import (Arabic and English headers). */
class PatientImportTemplateController extends Controller
{
    public function __invoke(PatientImport $import): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'dxa-template-').'.xlsx';
        $import->writeTemplate($path);

        return response()->download($path, 'doxamigo-patients-template.xlsx')->deleteFileAfterSend();
    }
}

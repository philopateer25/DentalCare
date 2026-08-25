<?php

use App\Http\Controllers\PatientOdontogramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 3D Odontogram Test Route
Route::get('/test-3d-odontogram/{patient?}', [PatientOdontogramController::class, 'showTest'])->name('odontogram.test');

// Patient Teeth API Routes
Route::get('/api/patients/{patient}/teeth', [PatientOdontogramController::class, 'getTeeth'])->name('api.patients.teeth.index');
Route::post('/api/patients/{patient}/teeth', [PatientOdontogramController::class, 'updateTooth'])->name('api.patients.teeth.store');

// --- WhatsApp Test Routes ---
Route::get('/test-whatsapp', function () {
    return view('test-whatsapp');
});

Route::post('/test-whatsapp', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'phone' => 'required|string',
        'message' => 'required|string',
    ]);

    $phone = $request->phone;
    
    // 1. Generate fake PDF (just to show it works, but we won't send it via ngrok)
    $html = '<h1>Fake Invoice</h1><p>This is a test invoice sent to ' . htmlspecialchars($phone) . '</p><p>Total: 500 EGP</p>';
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
    
    $fileName = 'test_invoice_' . time() . '.pdf';
    $path = "public/invoices/{$fileName}";
    \Illuminate\Support\Facades\Storage::disk('local')->put($path, $pdf->output());

    $service = new \App\Services\WhatsAppService();
    
    // 2. Send the text message synchronously
    $service->sendMessage($phone, $request->message);

    // 3. Send the document synchronously.
    // We use a dummy public PDF from W3C because your ngrok free tier blocks UltraMsg from downloading your local PDF!
    $caption = "Here is your test invoice!";
    $publicDummyPdf = "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";
    $result2 = $service->sendDocument($phone, $publicDummyPdf, $fileName, $caption);

    if ($result2) {
        return back()->with('success', "Messages dispatched successfully! The text and PDF should have arrived.");
    } else {
        return back()->with('success', "Text sent, but document failed! Please check laravel.log.");
    }
});

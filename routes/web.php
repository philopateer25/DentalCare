<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/patients/{patient?}/odontogram', [\App\Http\Controllers\PatientOdontogramController::class, 'showTest'])->name('patient.odontogram');
Route::get('/patients/{patient?}/odontogram-details', [\App\Http\Controllers\PatientOdontogramController::class, 'showDetails'])->name('patient.odontogram.details');
Route::post('/api/patients/{patient}/teeth', [\App\Http\Controllers\PatientOdontogramController::class, 'updateTooth']);

Route::get('/prescriptions/{prescription}/print', function (\App\Models\Prescription $prescription) {
    return view('prescriptions.print', compact('prescription'));
})->name('prescriptions.print');

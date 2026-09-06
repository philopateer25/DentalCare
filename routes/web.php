<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/patients/{patient?}/odontogram', [\App\Http\Controllers\PatientOdontogramController::class, 'showTest'])->name('patient.odontogram');
Route::get('/patients/{patient?}/odontogram-details', [\App\Http\Controllers\PatientOdontogramController::class, 'showDetails'])->name('patient.odontogram.details');
Route::post('/api/patients/{patient}/teeth', [\App\Http\Controllers\PatientOdontogramController::class, 'updateTooth']);

// Examination-based Odontogram API
Route::get('/api/patients/{patient}/examinations', [\App\Http\Controllers\DentalExaminationController::class, 'index']);
Route::post('/api/patients/{patient}/examinations', [\App\Http\Controllers\DentalExaminationController::class, 'store']);
Route::get('/api/examinations/{examination}/odontogram', [\App\Http\Controllers\DentalExaminationController::class, 'show']);
Route::post('/api/examinations/{examination}/findings', [\App\Http\Controllers\DentalExaminationController::class, 'updateFinding']);

// Treatment Planning Endpoints
Route::get('/api/patients/{patient}/treatment-plans', [\App\Http\Controllers\Api\TreatmentPlanController::class, 'index']);
Route::post('/api/patients/{patient}/treatment-plans', [\App\Http\Controllers\Api\TreatmentPlanController::class, 'store']);
Route::get('/api/patients/{patient}/treatment-plans/active', [\App\Http\Controllers\Api\TreatmentPlanController::class, 'getActivePlan']);
Route::post('/api/treatment-plans/{plan}/procedures', [\App\Http\Controllers\Api\TreatmentPlanController::class, 'addProcedure']);
Route::patch('/api/procedures/{procedure}/complete', function (\App\Models\TreatmentProcedure $procedure) {
    $procedure->update(['status' => 'completed']);
    return response()->json(['message' => 'Completed', 'procedure' => $procedure]);
});

Route::get('/prescriptions/{prescription}/print', function (\App\Models\Prescription $prescription) {
    return view('prescriptions.print', compact('prescription'));
})->name('prescriptions.print');

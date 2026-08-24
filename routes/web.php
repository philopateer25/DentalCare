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

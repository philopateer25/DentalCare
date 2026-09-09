<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PushSubscriptionController;

Route::get('/', function () {
    return Inertia::render('Welcome/Welcome');
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('dashboard');
})->name('dashboard');

Route::get('/patients', function () {
    return Inertia::render('patiants');
})->name('patients');

Route::get('/operations', function () {
    return Inertia::render('operations');
})->name('operations');

Route::get('/finance', function () {
    $practice = \App\Models\Practice::first();
    $tenantId = $practice?->id ?? 1;

    $invoices = \App\Models\Invoice::with(['patient'])->latest()->take(15)->get();
    $payments = \App\Models\Payment::with(['patient', 'invoice'])->latest()->take(15)->get();
    $installmentPlans = \App\Models\InstallmentPlan::with(['invoice.patient'])->latest()->take(15)->get();
    $doctorCommissions = \App\Models\DoctorCommission::with(['doctor', 'payment.patient'])->latest()->take(15)->get();
    $expenses = \App\Models\ClinicExpense::latest()->take(15)->get();

    $currentCurrency = \App\Services\CurrencyHelper::currentCurrency();
    $currencySymbol = \App\Services\CurrencyHelper::symbol($currentCurrency);

    $stats = [
        'totalProduction' => (float) \App\Models\Invoice::where('status', '!=', 'cancelled')->sum('total_amount'),
        'totalCollections' => (float) \App\Models\Payment::sum('amount'),
        'totalOutstandingAR' => (float) \App\Models\Invoice::where('status', '!=', 'cancelled')->sum('balance_due'),
        'totalExpenses' => (float) \App\Models\ClinicExpense::sum('amount'),
        'currency' => $currentCurrency,
        'currencySymbol' => $currencySymbol,
    ];

    return Inertia::render('finance', [
        'tenantId' => $tenantId,
        'stats' => $stats,
        'invoices' => $invoices,
        'payments' => $payments,
        'installmentPlans' => $installmentPlans,
        'commissions' => $doctorCommissions,
        'expenses' => $expenses,
        'availableCurrencies' => \App\Services\CurrencyHelper::CURRENCIES,
    ]);
})->name('finance');

Route::get('/insurance', function () {
    return Inertia::render('insurance');
})->name('insurance');

Route::get('/inventory', function () {
    return Inertia::render('inventory');
})->name('inventory');

Route::get('/labs', function () {
    return Inertia::render('labs');
})->name('labs');

Route::get('/locale/{lang}', function (string $lang) {
    if (in_array($lang, ['en', 'ar', 'fr'], true)) {
        session(['locale' => $lang]);
        if (auth()->check()) {
            auth()->user()->update(['locale' => $lang]);
        }
    }
    return redirect()->back();
})->name('locale.switch');

Route::middleware('auth')->post('/push/subscribe', [PushSubscriptionController::class, 'update'])->name('push.subscribe');

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

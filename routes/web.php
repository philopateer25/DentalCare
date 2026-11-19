<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Api\PracticeFeatureController;

Route::get('/', function () {
    return Inertia::render('Welcome/Welcome');
})->name('home');

Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');
Route::get('/register', fn () => redirect('/quick-login'));
Route::get('/auth/Register', fn () => redirect('/quick-login'));
Route::get('/auth/Login', fn () => redirect('/quick-login'));

Route::get('/quick-login', function (\Illuminate\Http\Request $request) {
    $user = \App\Models\User::where('email', 'admin@clinic.com')->first() 
        ?? \App\Models\User::where('email', 'doctor@dentalcare.com')->first()
        ?? \App\Models\User::first();
    auth()->login($user);
    $request->session()->regenerate();
    
    $redirect = $request->query('redirect', '/admin/1/patients/1');
    return redirect($redirect);
})->name('quick-login');

// Clinic Onboarding Routes
Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding/step', [OnboardingController::class, 'saveStep']);
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
});

Route::middleware(['auth', 'onboarding'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('/patients', function () {
        return Inertia::render('patiants');
    })->name('patients');

    Route::get('/operations', function () {
        return Inertia::render('operations');
    })->name('operations');

    Route::middleware('feature:finance')->get('/finance', function () {
        $tenantId = auth()->user()?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id ?? 1;

        $invoices = \App\Models\Invoice::where('practice_id', $tenantId)->with(['patient'])->latest()->take(15)->get();
        $payments = \App\Models\Payment::where('practice_id', $tenantId)->with(['patient', 'invoice'])->latest()->take(15)->get();
        $installmentPlans = \App\Models\InstallmentPlan::whereHas('invoice', fn ($q) => $q->where('practice_id', $tenantId))->with(['invoice.patient'])->latest()->take(15)->get();
        $doctorCommissions = \App\Models\DoctorCommission::where(function ($q) use ($tenantId) {
            $q->where('practice_id', $tenantId)
              ->orWhereHas('payment', fn ($p) => $p->where('practice_id', $tenantId));
        })->with(['doctor', 'payment.patient'])->latest()->take(15)->get();
        $expenses = \App\Models\ClinicExpense::where('practice_id', $tenantId)->latest()->take(15)->get();

        $currentCurrency = \App\Services\CurrencyHelper::currentCurrency();
        $currencySymbol = \App\Services\CurrencyHelper::symbol($currentCurrency);
        $financeService = app(\App\Services\Widgets\FinanceWidgetService::class);

        $stats = [
            'totalProduction' => (float) \App\Models\Invoice::where('practice_id', $tenantId)->where('status', '!=', 'cancelled')->sum('total_amount'),
            'totalCollections' => (float) \App\Models\Payment::where('practice_id', $tenantId)->sum('amount'),
            'totalOutstandingAR' => (float) \App\Models\Invoice::where('practice_id', $tenantId)->where('status', '!=', 'cancelled')->sum('balance_due'),
            'totalExpenses' => $financeService->getTotalExpenses($tenantId),
            'clinicExpenses' => $financeService->getTotalClinicExpenses($tenantId),
            'payrollExpenses' => $financeService->getTotalPayrollExpenses($tenantId),
            'commissionExpenses' => $financeService->getTotalCommissionExpenses($tenantId),
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

    Route::middleware('feature:insurance')->get('/insurance', function () {
        return Inertia::render('insurance');
    })->name('insurance');

    Route::middleware('feature:inventory')->get('/inventory', function () {
        return Inertia::render('inventory');
    })->name('inventory');

    Route::middleware('feature:labs')->get('/labs', function () {
        return Inertia::render('labs');
    })->name('labs');

    Route::get('/calendar', function () {
        return Inertia::render('calendar');
    })->name('calendar');

    Route::get('/analytics', function () {
        return Inertia::render('analytics');
    })->name('analytics');

    Route::get('/support', function () {
        return Inertia::render('support');
    })->name('support');
});

Route::middleware(['auth', 'feature:3d_model'])->group(function () {
    Route::get('/patients/{patient?}/odontogram', [\App\Http\Controllers\PatientOdontogramController::class, 'showTest'])->name('patient.odontogram');
    Route::get('/patients/{patient?}/odontogram-details', [\App\Http\Controllers\PatientOdontogramController::class, 'showDetails'])->name('patient.odontogram.details');
    Route::post('/api/patients/{patient}/teeth', [\App\Http\Controllers\PatientOdontogramController::class, 'updateTooth']);
});

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

// Odontogram, examination, treatment-plan and fee-schedule APIs (session auth required;
// these used to be reachable without login).
Route::middleware('auth')->group(function () {
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

    // Procedure Codes & Fee Schedule API
    Route::get('/api/procedure-codes', function () {
        return \App\Models\ProcedureCode::with('category')->orderBy('code')->get();
    });
    Route::patch('/api/procedure-codes/{procedureCode}', function (\App\Models\ProcedureCode $procedureCode, \Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'standard_fee' => 'required|numeric|min:0',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);
        $procedureCode->update($validated);
        return response()->json([
            'success' => true,
            'procedure' => $procedureCode->fresh('category'),
        ]);
    });
    Route::post('/api/procedure-codes', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'code' => 'required|string|unique:procedure_codes,code',
            'title' => 'required|string',
            'standard_fee' => 'required|numeric|min:0',
            'category_id' => 'required|exists:procedure_categories,id',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);
        $code = \App\Models\ProcedureCode::create($validated);
        return response()->json([
            'success' => true,
            'procedure' => $code->load('category'),
        ]);
    });
    Route::patch('/api/procedures/{procedure}/complete', [\App\Http\Controllers\Api\TreatmentProcedureController::class, 'complete']);
});

Route::get('/prescriptions/{prescription}/print', function (\App\Models\Prescription $prescription) {
    abort_unless($prescription->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant access.');
    return view('prescriptions.print', compact('prescription'));
})->middleware('auth')->name('prescriptions.print');

// Public & Secure Token Streaming Routes
Route::get('/voice-notes/stream/{share_token}', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'stream'])->name('voice-notes.stream');
Route::get('/voice-notes/download/{share_token}', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'download'])->name('voice-notes.download');

// Patient Voice Notes Management API
Route::middleware('auth')->group(function () {
    Route::post('/api/patients/{patient}/voice-notes', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'store']);
    Route::get('/api/patients/{patient}/voice-notes', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'index']);
    Route::get('/api/patients/{patient}/voice-notes/{voiceNote}/audio', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'staffStream']);
    Route::post('/api/patients/{patient}/voice-notes/{voiceNote}/revoke', [\App\Http\Controllers\Api\PatientVoiceNoteController::class, 'revoke']);
});

// Dashboard Widget, Practice Feature, Notification, Calendar, Analytics & Support Endpoints
Route::middleware('auth')->group(function () {
    Route::get('/api/dashboard/widgets', [\App\Http\Controllers\DashboardWidgetController::class, 'index']);
    Route::post('/api/dashboard/widgets/preferences', [\App\Http\Controllers\DashboardWidgetController::class, 'updatePreferences']);

    Route::get('/api/practice/features', [PracticeFeatureController::class, 'index']);
    Route::post('/api/practice/features', [PracticeFeatureController::class, 'update']);

    // Notifications Endpoints
    Route::get('/api/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/api/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/api/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // Detailed Calendar Endpoints
    Route::get('/api/calendar/appointments', [\App\Http\Controllers\Api\CalendarController::class, 'index']);
    Route::post('/api/calendar/appointments', [\App\Http\Controllers\Api\CalendarController::class, 'store']);
    Route::patch('/api/calendar/appointments/{appointment}/reschedule', [\App\Http\Controllers\Api\CalendarController::class, 'reschedule']);
    Route::patch('/api/calendar/appointments/{appointment}/status', [\App\Http\Controllers\Api\CalendarController::class, 'updateStatus']);

    // Analytics Endpoints
    Route::get('/api/analytics', [\App\Http\Controllers\Api\AnalyticsController::class, 'index']);

    // Support Ticket Endpoints
    Route::get('/api/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
    Route::post('/api/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'store']);
    Route::get('/api/support/tickets/{ticket}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show']);
    Route::post('/api/support/tickets/{ticket}/reply', [\App\Http\Controllers\Api\SupportTicketController::class, 'reply']);
    Route::patch('/api/support/tickets/{ticket}/status', [\App\Http\Controllers\Api\SupportTicketController::class, 'updateStatus']);
});

// WhatsApp Webhook Route
Route::post('/api/whatsapp/webhook', [\App\Http\Controllers\WhatsAppWebhookController::class, 'handle'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Mock Central License API (For Local Testing of Phase 1)
Route::post('/api/internal/license/enroll', [\App\Http\Controllers\Api\CentralLicenseMockController::class, 'enroll'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/api/internal/license/heartbeat', [\App\Http\Controllers\Api\CentralLicenseMockController::class, 'heartbeat'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

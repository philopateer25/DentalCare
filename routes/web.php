<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CentralLicenseMockController;
use App\Http\Controllers\Api\ChartWorkspaceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientVoiceNoteController;
use App\Http\Controllers\Api\PerioChartController;
use App\Http\Controllers\Api\PracticeFeatureController;
use App\Http\Controllers\Api\PracticeOptionController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\TreatmentProcedureController;
use App\Http\Controllers\ChartPrintController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\DentalExaminationController;
use App\Http\Controllers\LicenseExportController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaperCardScanController;
use App\Http\Controllers\PatientFileDownloadController;
use App\Http\Controllers\PatientImportTemplateController;
use App\Http\Controllers\PatientOdontogramController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\ClinicExpense;
use App\Models\DoctorCommission;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\ProcedureCode;
use App\Models\User;
use App\Services\CurrencyHelper;
use App\Services\Widgets\FinanceWidgetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome/Welcome');
})->name('home');

Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');
// Developer shortcuts ONLY. /quick-login signs anyone in as an admin without a
// password, so it must never exist on a clinic install (APP_ENV=production).
if (app()->environment('local')) {
    Route::get('/register', fn () => redirect('/quick-login'));
    Route::get('/auth/Register', fn () => redirect('/quick-login'));
    Route::get('/auth/Login', fn () => redirect('/quick-login'));

    Route::get('/quick-login', function (Request $request) {
        $user = User::where('email', 'admin@clinic.com')->first()
            ?? User::where('email', 'doctor@dentalcare.com')->first()
            ?? User::first();
        auth()->login($user);
        $request->session()->regenerate();

        $redirect = $request->query('redirect', '/admin/1/patients/1');

        return redirect($redirect);
    })->name('quick-login');
} else {
    Route::get('/register', fn () => redirect()->route('filament.admin.auth.login'));
    Route::get('/auth/Register', fn () => redirect()->route('filament.admin.auth.login'));
    Route::get('/auth/Login', fn () => redirect()->route('filament.admin.auth.login'));
}

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
        $tenantId = auth()->user()?->practice_id ?? Filament::getTenant()?->id ?? 1;

        $invoices = Invoice::where('practice_id', $tenantId)->with(['patient'])->latest()->take(15)->get();
        $payments = Payment::where('practice_id', $tenantId)->with(['patient', 'invoice'])->latest()->take(15)->get();
        $installmentPlans = InstallmentPlan::whereHas('invoice', fn ($q) => $q->where('practice_id', $tenantId))->with(['invoice.patient'])->latest()->take(15)->get();
        $doctorCommissions = DoctorCommission::where(function ($q) use ($tenantId) {
            $q->where('practice_id', $tenantId)
                ->orWhereHas('payment', fn ($p) => $p->where('practice_id', $tenantId));
        })->with(['doctor', 'payment.patient'])->latest()->take(15)->get();
        $expenses = ClinicExpense::where('practice_id', $tenantId)->latest()->take(15)->get();

        $currentCurrency = CurrencyHelper::currentCurrency();
        $currencySymbol = CurrencyHelper::symbol($currentCurrency);
        $financeService = app(FinanceWidgetService::class);

        $stats = [
            'totalProduction' => (float) Invoice::where('practice_id', $tenantId)->where('status', '!=', 'cancelled')->sum('total_amount'),
            'totalCollections' => (float) Payment::where('practice_id', $tenantId)->sum('amount'),
            'totalOutstandingAR' => (float) Invoice::where('practice_id', $tenantId)->where('status', '!=', 'cancelled')->sum('balance_due'),
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
            'availableCurrencies' => CurrencyHelper::CURRENCIES,
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
    Route::get('/patients/{patient?}/odontogram', [PatientOdontogramController::class, 'showTest'])->name('patient.odontogram');
    Route::get('/patients/{patient?}/odontogram-details', [PatientOdontogramController::class, 'showDetails'])->name('patient.odontogram.details');
    Route::post('/api/patients/{patient}/teeth', [PatientOdontogramController::class, 'updateTooth']);
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

// Clinic data export — reachable even when the license is read-only or locked.
Route::middleware('auth')->get('/license/export', LicenseExportController::class)->name('license.export');

// Phase 4 — switching from paper.
// Phone side of "Scan paper card": no login, the one-time token is the key.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/scan/{token}', [PaperCardScanController::class, 'show'])->name('paper-card.show');
    Route::post('/scan/{token}', [PaperCardScanController::class, 'store'])->name('paper-card.store');
});
Route::middleware('auth')->group(function () {
    Route::get('/patients/import/template', PatientImportTemplateController::class)->name('patients.import.template');
    Route::get('/patient-files/{patientFile}/download', PatientFileDownloadController::class)->name('patient-files.download');
});

// Odontogram, examination, treatment-plan and fee-schedule APIs (session auth required;
// these used to be reachable without login).
Route::middleware('auth')->group(function () {
    // Examination-based Odontogram API
    Route::get('/api/patients/{patient}/examinations', [DentalExaminationController::class, 'index']);
    Route::post('/api/patients/{patient}/examinations', [DentalExaminationController::class, 'store']);
    Route::get('/api/examinations/{examination}/odontogram', [DentalExaminationController::class, 'show']);
    Route::post('/api/examinations/{examination}/findings', [DentalExaminationController::class, 'updateFinding']);

    // Treatment Planning Endpoints
    Route::get('/api/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'index']);
    Route::post('/api/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'store']);
    Route::get('/api/patients/{patient}/treatment-plans/active', [TreatmentPlanController::class, 'getActivePlan']);
    Route::post('/api/treatment-plans/{plan}/procedures', [TreatmentPlanController::class, 'addProcedure']);

    // Chart workspace (Filament: Patients -> Chart) and its printable summary
    Route::get('/api/patients/{patient}/chart-workspace', [ChartWorkspaceController::class, 'show']);
    Route::get('/patients/{patient}/chart/print', [ChartPrintController::class, 'show'])->name('patient.chart.print');

    // Phase 5: the clinic's own pick-or-add lists for the chart workspace
    Route::get('/api/practice-options', [PracticeOptionController::class, 'index'])->name('practice-options.index');
    Route::post('/api/practice-options', [PracticeOptionController::class, 'store'])->name('practice-options.store');

    // 6-site periodontal (gum) chart
    Route::get('/api/patients/{patient}/perio', [PerioChartController::class, 'index']);
    Route::post('/api/patients/{patient}/perio', [PerioChartController::class, 'store']);
    Route::put('/api/perio/{chart}', [PerioChartController::class, 'update']);

    // Procedure Codes & Fee Schedule API
    Route::get('/api/procedure-codes', function () {
        return ProcedureCode::with('category')->orderBy('code')->get();
    });
    Route::patch('/api/procedure-codes/{procedureCode}', function (ProcedureCode $procedureCode, Request $request) {
        // The fee schedule is clinic-wide: only admins may change it (it used to be editable by any user).
        abort_unless(auth()->user()->hasAnyRole(['clinic_admin', 'super_admin', 'developer']), 403, 'Only clinic admins can change the fee schedule.');
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
    Route::post('/api/procedure-codes', function (Request $request) {
        abort_unless(auth()->user()->hasAnyRole(['clinic_admin', 'super_admin', 'developer']), 403, 'Only clinic admins can change the fee schedule.');
        $validated = $request->validate([
            'code' => 'required|string|unique:procedure_codes,code',
            'title' => 'required|string',
            'standard_fee' => 'required|numeric|min:0',
            'category_id' => 'required|exists:procedure_categories,id',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);
        $code = ProcedureCode::create($validated);

        return response()->json([
            'success' => true,
            'procedure' => $code->load('category'),
        ]);
    });
    Route::patch('/api/procedures/{procedure}/complete', [TreatmentProcedureController::class, 'complete']);
});

Route::get('/prescriptions/{prescription}/print', function (Prescription $prescription) {
    // Prescriptions have no practice_id column; ownership comes from the patient.
    abort_unless($prescription->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant access.');

    return view('prescriptions.print', compact('prescription'));
})->middleware('auth')->name('prescriptions.print');

// Public & Secure Token Streaming Routes
Route::get('/voice-notes/stream/{share_token}', [PatientVoiceNoteController::class, 'stream'])->name('voice-notes.stream');
Route::get('/voice-notes/download/{share_token}', [PatientVoiceNoteController::class, 'download'])->name('voice-notes.download');

// Patient Voice Notes Management API
Route::middleware('auth')->group(function () {
    Route::post('/api/patients/{patient}/voice-notes', [PatientVoiceNoteController::class, 'store']);
    Route::get('/api/patients/{patient}/voice-notes', [PatientVoiceNoteController::class, 'index']);
    Route::get('/api/patients/{patient}/voice-notes/{voiceNote}/audio', [PatientVoiceNoteController::class, 'staffStream']);
    Route::post('/api/patients/{patient}/voice-notes/{voiceNote}/revoke', [PatientVoiceNoteController::class, 'revoke']);
});

// Dashboard Widget, Practice Feature, Notification, Calendar, Analytics & Support Endpoints
Route::middleware('auth')->group(function () {
    Route::get('/api/dashboard/widgets', [DashboardWidgetController::class, 'index']);
    Route::post('/api/dashboard/widgets/preferences', [DashboardWidgetController::class, 'updatePreferences']);

    Route::get('/api/practice/features', [PracticeFeatureController::class, 'index']);
    Route::post('/api/practice/features', [PracticeFeatureController::class, 'update']);

    // Notifications Endpoints
    Route::get('/api/notifications', [NotificationController::class, 'index']);
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Detailed Calendar Endpoints
    Route::get('/api/calendar/appointments', [CalendarController::class, 'index']);
    Route::post('/api/calendar/appointments', [CalendarController::class, 'store']);
    Route::patch('/api/calendar/appointments/{appointment}/reschedule', [CalendarController::class, 'reschedule']);
    Route::patch('/api/calendar/appointments/{appointment}/status', [CalendarController::class, 'updateStatus']);

    // Analytics Endpoints
    Route::get('/api/analytics', [AnalyticsController::class, 'index']);

    // Support Ticket Endpoints
    Route::get('/api/support/tickets', [SupportTicketController::class, 'index']);
    Route::post('/api/support/tickets', [SupportTicketController::class, 'store']);
    Route::get('/api/support/tickets/{ticket}', [SupportTicketController::class, 'show']);
    Route::post('/api/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply']);
    Route::patch('/api/support/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus']);
});

// WhatsApp Webhook Route
Route::post('/api/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);

// Mock Central License API (For Local Testing of Phase 1)
Route::post('/api/internal/license/enroll', [CentralLicenseMockController::class, 'enroll'])->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('/api/internal/license/heartbeat', [CentralLicenseMockController::class, 'heartbeat'])->withoutMiddleware([VerifyCsrfToken::class]);

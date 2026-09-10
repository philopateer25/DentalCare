<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public const VALID_STATUSES = [
        'booked',
        'arrived',
        'in_chair',
        'completed',
        'no_show',
        'cancelled',
    ];

    /**
     * Get tenant-isolated appointments filtered by date range, doctor, operatory, and status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $practiceId = $user->practice_id;

        if (!$practiceId) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $startDate = $request->input('start') ? Carbon::parse($request->input('start'))->startOfDay() : now()->startOfMonth();
        $endDate = $request->input('end') ? Carbon::parse($request->input('end'))->endOfDay() : now()->endOfMonth();

        $query = Appointment::where('practice_id', $practiceId)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->with(['patient', 'doctor', 'operatory.branch', 'procedure']);

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', (int) $request->input('doctor_id'));
        }

        if ($request->filled('operatory_id')) {
            $query->where('operatory_id', (int) $request->input('operatory_id'));
        }

        if ($request->filled('status') && in_array($request->input('status'), self::VALID_STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }

        $appointments = $query->orderBy('start_time', 'asc')->get()->map(function ($app) {
            return [
                'id' => $app->id,
                'title' => ($app->patient?->full_name ?? 'Patient') . ($app->procedure_name ? " - {$app->procedure_name}" : ''),
                'patient_id' => $app->patient_id,
                'patient_name' => $app->patient?->full_name ?? 'Unknown',
                'doctor_id' => $app->doctor_id,
                'doctor_name' => $app->doctor?->name ?? 'Unassigned Doctor',
                'operatory_id' => $app->operatory_id,
                'operatory_name' => $app->operatory?->name ?? 'Operatory',
                'branch_id' => $app->branch_id,
                'start_time' => $app->start_time?->toIso8601String(),
                'end_time' => $app->end_time?->toIso8601String(),
                'status' => $app->status ?? 'booked',
                'consultation_fee' => (float) ($app->consultation_fee ?? 0),
                'chief_complaint' => $app->chief_complaint,
                'procedure_name' => $app->procedure_name,
                'notes' => $app->notes,
            ];
        });

        // Filter dropdown options
        $doctors = User::where('practice_id', $practiceId)
            ->whereIn('role', ['doctor', 'dentist', 'clinic_admin', 'super_admin'])
            ->get(['id', 'name', 'role']);

        $operatories = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))
            ->get(['id', 'name', 'branch_id']);

        $patients = Patient::where('practice_id', $practiceId)
            ->get(['id', 'first_name', 'last_name', 'phone']);

        return response()->json([
            'appointments' => $appointments,
            'doctors' => $doctors,
            'operatories' => $operatories,
            'patients' => $patients,
            'statuses' => self::VALID_STATUSES,
        ]);
    }

    /**
     * Store new appointment with conflict validation.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $practiceId = $user->practice_id;

        if (!$practiceId) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'doctor_id' => 'required|integer|exists:users,id',
            'operatory_id' => 'required|integer|exists:operatories,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'chief_complaint' => 'nullable|string|max:500',
            'procedure_name' => 'nullable|string|max:255',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:' . implode(',', self::VALID_STATUSES),
            'notes' => 'nullable|string|max:1000',
        ]);

        // Security check: Ensure patient, doctor, and operatory belong to current tenant practice
        $patient = Patient::where('id', $validated['patient_id'])->where('practice_id', $practiceId)->firstOrFail();
        $doctor = User::where('id', $validated['doctor_id'])->where('practice_id', $practiceId)->firstOrFail();
        $operatory = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))
            ->where('id', $validated['operatory_id'])
            ->firstOrFail();

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // Check for time collisions (same doctor OR same operatory)
        $hasConflict = Appointment::where('practice_id', $practiceId)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($doctor, $operatory) {
                $q->where('doctor_id', $doctor->id)
                  ->orWhere('operatory_id', $operatory->id);
            })
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Time slot conflict: The selected doctor or operatory/chair is already booked during this time interval.',
            ], 422);
        }

        $appointment = Appointment::create([
            'practice_id' => $practiceId,
            'branch_id' => $operatory->branch_id,
            'operatory_id' => $operatory->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'chief_complaint' => $validated['chief_complaint'] ?? null,
            'procedure_name' => $validated['procedure_name'] ?? null,
            'consultation_fee' => $validated['consultation_fee'] ?? 0,
            'status' => $validated['status'] ?? 'booked',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Trigger appointment reminder notification
        NotificationService::notifyAppointmentReminder($appointment);

        return response()->json([
            'message' => 'Appointment scheduled successfully.',
            'appointment' => $appointment->fresh(['patient', 'doctor', 'operatory']),
        ], 201);
    }

    /**
     * Reschedule appointment with conflict validation.
     */
    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        $practiceId = $user->practice_id;

        if ((int) $appointment->practice_id !== (int) $practiceId) {
            return response()->json(['message' => 'Unauthorized cross-tenant access.'], 403);
        }

        $validated = $request->validate([
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'operatory_id' => 'nullable|integer|exists:operatories,id',
        ]);

        $doctorId = $validated['doctor_id'] ?? $appointment->doctor_id;
        $operatoryId = $validated['operatory_id'] ?? $appointment->operatory_id;
        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // Check for time collisions excluding current appointment
        $hasConflict = Appointment::where('practice_id', $practiceId)
            ->where('id', '!=', $appointment->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($doctorId, $operatoryId) {
                $q->where('doctor_id', $doctorId)
                  ->orWhere('operatory_id', $operatoryId);
            })
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Time slot conflict: The selected doctor or operatory/chair is already booked during this time interval.',
            ], 422);
        }

        $appointment->update([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'doctor_id' => $doctorId,
            'operatory_id' => $operatoryId,
        ]);

        return response()->json([
            'message' => 'Appointment rescheduled successfully.',
            'appointment' => $appointment->fresh(['patient', 'doctor', 'operatory']),
        ]);
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if ((int) $appointment->practice_id !== (int) $user->practice_id) {
            return response()->json(['message' => 'Unauthorized cross-tenant access.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', self::VALID_STATUSES),
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment->update([
            'status' => $validated['status'],
            'cancellation_reason' => $validated['cancellation_reason'] ?? $appointment->cancellation_reason,
        ]);

        if ($validated['status'] === 'completed' && $appointment->procedure) {
            NotificationService::notifyProcedureCompleted($appointment->procedure);
        }

        return response()->json([
            'message' => "Appointment status updated to {$validated['status']}.",
            'appointment' => $appointment->fresh(['patient', 'doctor', 'operatory']),
        ]);
    }
}

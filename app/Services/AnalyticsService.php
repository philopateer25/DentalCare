<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorCommission;
use App\Models\Invoice;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentProcedure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get complete tenant analytics dashboard metrics.
     */
    public function getMetrics(int $practiceId, ?Carbon $startDate = null, ?Carbon $endDate = null, ?int $branchId = null): array
    {
        $endDate = $endDate ?? now()->endOfDay();
        $startDate = $startDate ?? (clone $endDate)->subMonths(5)->startOfMonth();

        return [
            'summary' => [
                'total_production' => $this->getGrossProduction($practiceId, $startDate, $endDate, $branchId),
                'total_collections' => $this->getCollections($practiceId, $startDate, $endDate, $branchId),
                'outstanding_ar' => $this->getOutstandingAR($practiceId, $branchId),
                'total_patients' => Patient::where('practice_id', $practiceId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            ],
            'production_vs_collections' => $this->getProductionVsCollectionsTrend($practiceId, $startDate, $endDate, $branchId),
            'no_show_stats' => $this->getNoShowStats($practiceId, $startDate, $endDate, $branchId),
            'operatory_utilization' => $this->getOperatoryUtilization($practiceId, $startDate, $endDate, $branchId),
            'top_procedures_by_volume' => $this->getTopProceduresByVolume($practiceId, $startDate, $endDate, $branchId),
            'top_procedures_by_revenue' => $this->getTopProceduresByRevenue($practiceId, $startDate, $endDate, $branchId),
            'doctor_leaderboard' => $this->getDoctorLeaderboard($practiceId, $startDate, $endDate),
            'patient_acquisition' => $this->getPatientAcquisitionTrend($practiceId, $startDate, $endDate, $branchId),
            'branches' => Branch::where('practice_id', $practiceId)->get(['id', 'name']),
        ];
    }

    public function getGrossProduction(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): float
    {
        return (float) Invoice::where('practice_id', $practiceId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issue_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($branchId, fn ($q) => $q->where(function ($sub) use ($branchId) {
                $sub->whereHas('patient', fn ($pq) => $pq->where('branch_id', $branchId))
                    ->orWhereHas('treatmentPlan.doctor', fn ($dq) => $dq->where('branch_id', $branchId));
            }))
            ->sum('total_amount');
    }

    public function getCollections(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): float
    {
        return (float) Payment::where('practice_id', $practiceId)
            ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($branchId, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where(function ($sub) use ($branchId) {
                $sub->whereHas('patient', fn ($pq) => $pq->where('branch_id', $branchId))
                    ->orWhereHas('treatmentPlan.doctor', fn ($dq) => $dq->where('branch_id', $branchId));
            })))
            ->sum('amount');
    }

    public function getOutstandingAR(int $practiceId, ?int $branchId = null): float
    {
        return (float) Invoice::where('practice_id', $practiceId)
            ->where('status', '!=', 'cancelled')
            ->when($branchId, fn ($q) => $q->where(function ($sub) use ($branchId) {
                $sub->whereHas('patient', fn ($pq) => $pq->where('branch_id', $branchId))
                    ->orWhereHas('treatmentPlan.doctor', fn ($dq) => $dq->where('branch_id', $branchId));
            }))
            ->sum('balance_due');
    }

    /**
     * Production vs Collections trend by month.
     */
    public function getProductionVsCollectionsTrend(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $trend = [];
        $cursor = (clone $startDate)->startOfMonth();

        while ($cursor <= $endDate) {
            $monthStart = (clone $cursor)->startOfMonth();
            $monthEnd = (clone $cursor)->endOfMonth();

            $production = (float) Invoice::where('practice_id', $practiceId)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->when($branchId, fn ($q) => $q->whereHas('patient', fn ($pq) => $pq->where('branch_id', $branchId)))
                ->sum('total_amount');

            $collections = (float) Payment::where('practice_id', $practiceId)
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->when($branchId, fn ($q) => $q->whereHas('patient', fn ($pq) => $pq->where('branch_id', $branchId)))
                ->sum('amount');

            $trend[] = [
                'month' => $monthStart->format('M Y'),
                'production' => $production,
                'collections' => $collections,
            ];

            $cursor->addMonth();
        }

        return $trend;
    }

    /**
     * No-show rate and appointment status distribution.
     */
    public function getNoShowStats(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $query = Appointment::where('practice_id', $practiceId)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $total = $query->count();
        $noShows = (clone $query)->where('status', 'no_show')->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();
        $booked = (clone $query)->where('status', 'booked')->count();

        $noShowRate = $total > 0 ? round(($noShows / $total) * 100, 1) : 0.0;

        return [
            'total_appointments' => $total,
            'no_shows' => $noShows,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'booked' => $booked,
            'no_show_rate' => $noShowRate,
        ];
    }

    /**
     * Chair / Operatory utilization rate.
     */
    public function getOperatoryUtilization(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $operatories = Operatory::whereHas('branch', function ($q) use ($practiceId, $branchId) {
            $q->where('practice_id', $practiceId);
            if ($branchId) {
                $q->where('id', $branchId);
            }
        })->get();

        $daysCount = max(1, $startDate->diffInDays($endDate) + 1);
        $totalCapacityHoursPerOperatory = $daysCount * 8; // Assumes 8 operational hours per day

        $utilizationData = [];

        foreach ($operatories as $op) {
            $appointments = Appointment::where('operatory_id', $op->id)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

            $bookedMinutes = 0;
            foreach ($appointments as $app) {
                if ($app->start_time && $app->end_time) {
                    $bookedMinutes += max(0, $app->start_time->diffInMinutes($app->end_time));
                }
            }

            $bookedHours = round($bookedMinutes / 60, 1);
            $rate = $totalCapacityHoursPerOperatory > 0
                ? min(100.0, round(($bookedHours / $totalCapacityHoursPerOperatory) * 100, 1))
                : 0.0;

            $utilizationData[] = [
                'operatory_id' => $op->id,
                'operatory_name' => $op->name,
                'booked_hours' => $bookedHours,
                'capacity_hours' => $totalCapacityHoursPerOperatory,
                'utilization_rate' => $rate,
                'appointment_count' => $appointments->count(),
            ];
        }

        return $utilizationData;
    }

    /**
     * Top procedures by volume.
     */
    public function getTopProceduresByVolume(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        return TreatmentProcedure::whereHas('phase.plan.patient', function ($q) use ($practiceId, $branchId) {
            $q->where('practice_id', $practiceId);
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        })
        ->whereBetween('treatment_procedures.created_at', [$startDate, $endDate])
        ->join('procedure_codes', 'treatment_procedures.procedure_code_id', '=', 'procedure_codes.id')
        ->select('procedure_codes.title as procedure_name', DB::raw('COUNT(treatment_procedures.id) as volume'))
        ->groupBy('procedure_codes.id', 'procedure_codes.title')
        ->orderByDesc('volume')
        ->take(5)
        ->get()
        ->map(fn ($r) => [
            'procedure_name' => $r->procedure_name ?? 'General Procedure',
            'volume' => (int) $r->volume,
        ])
        ->toArray();
    }

    /**
     * Top procedures by revenue.
     */
    public function getTopProceduresByRevenue(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        return TreatmentProcedure::whereHas('phase.plan.patient', function ($q) use ($practiceId, $branchId) {
            $q->where('practice_id', $practiceId);
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        })
        ->whereBetween('treatment_procedures.created_at', [$startDate, $endDate])
        ->join('procedure_codes', 'treatment_procedures.procedure_code_id', '=', 'procedure_codes.id')
        ->select('procedure_codes.title as procedure_name', DB::raw('SUM(treatment_procedures.net_amount) as revenue'))
        ->groupBy('procedure_codes.id', 'procedure_codes.title')
        ->orderByDesc('revenue')
        ->take(5)
        ->get()
        ->map(fn ($r) => [
            'procedure_name' => $r->procedure_name ?? 'General Procedure',
            'revenue' => (float) $r->revenue,
        ])
        ->toArray();
    }

    /**
     * Doctor Commission & Performance Leaderboard.
     */
    public function getDoctorLeaderboard(int $practiceId, Carbon $startDate, Carbon $endDate): array
    {
        return DoctorCommission::whereHas('payment', fn ($q) => $q->where('practice_id', $practiceId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('doctor_id', DB::raw('SUM(gross_amount) as total_production'), DB::raw('SUM(commission_amount) as total_commission'))
            ->groupBy('doctor_id')
            ->orderByDesc('total_commission')
            ->with('doctor:id,name')
            ->get()
            ->map(fn ($row) => [
                'doctor_id' => $row->doctor_id,
                'doctor_name' => $row->doctor?->name ?? 'Doctor #' . $row->doctor_id,
                'total_production' => (float) $row->total_production,
                'total_commission' => (float) $row->total_commission,
            ])
            ->toArray();
    }

    /**
     * Patient Acquisition Trend.
     */
    public function getPatientAcquisitionTrend(int $practiceId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $trend = [];
        $cursor = (clone $startDate)->startOfMonth();

        while ($cursor <= $endDate) {
            $monthStart = (clone $cursor)->startOfMonth();
            $monthEnd = (clone $cursor)->endOfMonth();

            $newPatients = Patient::where('practice_id', $practiceId)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $trend[] = [
                'month' => $monthStart->format('M Y'),
                'new_patients' => $newPatients,
            ];

            $cursor->addMonth();
        }

        return $trend;
    }
}

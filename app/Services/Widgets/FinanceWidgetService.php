<?php

namespace App\Services\Widgets;

use App\Models\DoctorCommission;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class FinanceWidgetService
{
    /**
     * Get Gross Production for practice (Sum of non-cancelled invoice totals).
     */
    public function getGrossProduction(int $practiceId): float
    {
        return (float) Invoice::where('practice_id', $practiceId)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
    }

    /**
     * Get Collections for current month for practice.
     */
    public function getCollectionsThisMonth(int $practiceId, ?Carbon $date = null): float
    {
        $date = $date ?? now();

        return (float) Payment::where('practice_id', $practiceId)
            ->whereYear('paid_at', $date->year)
            ->whereMonth('paid_at', $date->month)
            ->sum('amount');
    }

    /**
     * Get Total Collections (all time) for practice.
     */
    public function getTotalCollections(int $practiceId): float
    {
        return (float) Payment::where('practice_id', $practiceId)->sum('amount');
    }

    /**
     * Get Total Outstanding Accounts Receivable for practice.
     */
    public function getOutstandingBalance(int $practiceId): float
    {
        return (float) Invoice::where('practice_id', $practiceId)
            ->where('status', '!=', 'cancelled')
            ->sum('balance_due');
    }

    /**
     * Get Doctor Commissions summary and top earners for practice.
     */
    public function getDoctorCommissions(int $practiceId): array
    {
        $query = DoctorCommission::whereHas('payment', function ($q) use ($practiceId) {
            $q->where('practice_id', $practiceId);
        });

        $totalCommissions = (float) $query->sum('commission_amount');

        $topEarners = DoctorCommission::whereHas('payment', function ($q) use ($practiceId) {
            $q->where('practice_id', $practiceId);
        })
        ->selectRaw('doctor_id, SUM(commission_amount) as total_earned')
        ->groupBy('doctor_id')
        ->orderByDesc('total_earned')
        ->with('doctor:id,name')
        ->take(5)
        ->get()
        ->map(fn ($row) => [
            'doctor_id' => $row->doctor_id,
            'doctor_name' => $row->doctor?->name ?? 'Doctor #' . $row->doctor_id,
            'total_earned' => (float) $row->total_earned,
        ])
        ->toArray();

        return [
            'total_commissions' => $totalCommissions,
            'top_earners' => $topEarners,
        ];
    }
}

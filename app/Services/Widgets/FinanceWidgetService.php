<?php

namespace App\Services\Widgets;

use App\Models\DoctorCommission;
use App\Models\Invoice;
use App\Models\Payment;
use DateTimeInterface;
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
    public function getCollectionsThisMonth(int $practiceId, ?DateTimeInterface $date = null): float
    {
        $date = $date ? Carbon::parse($date) : now();

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

    /**
     * Get Total Operating Expenses (Clinic Expenses + Disbursed Payroll + Settled Doctor Commissions).
     */
    public function getTotalExpenses(int $practiceId): float
    {
        return $this->getTotalClinicExpenses($practiceId) 
             + $this->getTotalPayrollExpenses($practiceId) 
             + $this->getTotalCommissionExpenses($practiceId);
    }

    public function getTotalClinicExpenses(int $practiceId): float
    {
        return (float) \App\Models\ClinicExpense::where('practice_id', $practiceId)->sum('amount');
    }

    public function getTotalPayrollExpenses(int $practiceId): float
    {
        return (float) \App\Models\PayrollSlip::where('practice_id', $practiceId)
            ->where('status', 'disbursed')
            ->sum('net_salary');
    }

    public function getTotalCommissionExpenses(int $practiceId): float
    {
        return (float) \App\Models\DoctorCommission::where(function ($q) use ($practiceId) {
                $q->where('practice_id', $practiceId)
                  ->orWhereHas('payment', fn ($p) => $p->where('practice_id', $practiceId));
            })
            ->where('status', 'settled')
            ->whereNull('payroll_slip_id')
            ->sum('commission_amount');
    }

    /**
     * Get Monthly Operating Expenses for practice (Expenses + Disbursed Payroll + Settled Commissions this month).
     */
    public function getMonthlyExpenses(int $practiceId, ?DateTimeInterface $date = null): float
    {
        return $this->getMonthlyClinicExpenses($practiceId, $date) 
             + $this->getMonthlyPayrollExpenses($practiceId, $date) 
             + $this->getMonthlyCommissionExpenses($practiceId, $date);
    }

    public function getMonthlyClinicExpenses(int $practiceId, ?DateTimeInterface $date = null): float
    {
        $date = $date ? Carbon::parse($date) : now();

        return (float) \App\Models\ClinicExpense::where('practice_id', $practiceId)
            ->whereYear('expense_date', $date->year)
            ->whereMonth('expense_date', $date->month)
            ->sum('amount');
    }

    public function getMonthlyPayrollExpenses(int $practiceId, ?DateTimeInterface $date = null): float
    {
        $date = $date ? Carbon::parse($date) : now();

        return (float) \App\Models\PayrollSlip::where('practice_id', $practiceId)
            ->where('status', 'disbursed')
            ->where('pay_period_year', $date->year)
            ->where('pay_period_month', $date->month)
            ->sum('net_salary');
    }

    public function getMonthlyCommissionExpenses(int $practiceId, ?DateTimeInterface $date = null): float
    {
        $date = $date ? Carbon::parse($date) : now();

        return (float) \App\Models\DoctorCommission::where(function ($q) use ($practiceId) {
                $q->where('practice_id', $practiceId)
                  ->orWhereHas('payment', fn ($p) => $p->where('practice_id', $practiceId));
            })
            ->where('status', 'settled')
            ->whereNull('payroll_slip_id')
            ->whereYear('settled_at', $date->year)
            ->whereMonth('settled_at', $date->month)
            ->sum('commission_amount');
    }

    /**
     * Get Monthly Net Operating Profit (Collections - Monthly Expenses).
     */
    public function getMonthlyNetProfit(int $practiceId, ?DateTimeInterface $date = null): float
    {
        return $this->getCollectionsThisMonth($practiceId, $date) - $this->getMonthlyExpenses($practiceId, $date);
    }
}

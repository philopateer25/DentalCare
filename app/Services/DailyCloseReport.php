<?php

namespace App\Services;

use App\Models\ClinicExpense;
use App\Models\DoctorCommission;
use App\Models\InstallmentSchedule;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * End-of-day owner report (Phase 3): what came in today by method, what went
 * out, what patients still owe, which installments are due, and each
 * doctor's commission. Everything is scoped to one practice.
 */
class DailyCloseReport
{
    public const METHOD_LABELS = [
        'cash' => 'Cash',
        'card_pos' => 'Card (POS)',
        'instapay' => 'InstaPay',
        'bank_transfer' => 'Bank transfer',
        'insurance_tpa' => 'Insurance',
    ];

    /**
     * @return array{
     *   date: string,
     *   collected: array<string, float>, collected_total: float, payments_count: int,
     *   expenses: array<string, float>, expenses_total: float,
     *   net_cash: float,
     *   outstanding_total: float, outstanding_count: int,
     *   installments_due_today: float, installments_overdue: float, installments_due_count: int,
     *   commissions: list<array{doctor: string, amount: float}>, commissions_total: float
     * }
     */
    public function build(int $practiceId, ?Carbon $day = null): array
    {
        $day = ($day ?? now())->copy()->timezone(config('app.timezone'));
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $date = $start->toDateString();

        $collected = Payment::query()
            ->where('practice_id', $practiceId)
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('payment_method')
            ->get();

        $expenses = ClinicExpense::query()
            ->where('practice_id', $practiceId)
            ->whereDate('expense_date', $date)
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();

        $collectedByMethod = $collected->mapWithKeys(fn ($row) => [($row->payment_method ?: 'cash') => round((float) $row->total, 2)])->all();

        $outstanding = Invoice::query()
            ->where('practice_id', $practiceId)
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['cancelled', 'paid']);

        $schedules = InstallmentSchedule::query()
            ->whereHas('plan.invoice', fn ($q) => $q->where('practice_id', $practiceId))
            ->whereNotIn('status', ['paid'])
            ->whereDate('due_date', '<=', $date)
            ->get(['due_date', 'amount', 'paid_amount']);

        $remaining = fn ($s) => max(0, (float) $s->amount - (float) $s->paid_amount);
        $dueToday = $schedules->filter(fn ($s) => $s->due_date->toDateString() === $date);
        $overdue = $schedules->filter(fn ($s) => $s->due_date->toDateString() < $date);

        $commissions = DoctorCommission::query()
            ->where('practice_id', $practiceId)
            ->whereBetween('created_at', [$start, $end])
            ->with('doctor:id,name')
            ->get()
            ->groupBy('doctor_id')
            ->map(fn ($rows) => [
                'doctor' => (string) ($rows->first()->doctor?->name ?? __('Unknown doctor')),
                'amount' => round((float) $rows->sum('commission_amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $collectedTotal = round(array_sum($collectedByMethod), 2);
        $expensesTotal = round(array_sum($expenses), 2);

        return [
            'date' => $date,
            'collected' => $collectedByMethod,
            'collected_total' => $collectedTotal,
            'payments_count' => (int) $collected->sum('n'),
            'expenses' => $expenses,
            'expenses_total' => $expensesTotal,
            'net_cash' => round(($collectedByMethod['cash'] ?? 0) - ($expenses['cash'] ?? 0), 2),
            'outstanding_total' => round((float) (clone $outstanding)->sum('balance_due'), 2),
            'outstanding_count' => (clone $outstanding)->count(),
            'installments_due_today' => round($dueToday->sum($remaining), 2),
            'installments_overdue' => round($overdue->sum($remaining), 2),
            'installments_due_count' => $schedules->count(),
            'commissions' => $commissions,
            'commissions_total' => round(array_sum(array_column($commissions, 'amount')), 2),
        ];
    }
}

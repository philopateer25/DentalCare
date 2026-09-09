<?php

namespace App\Filament\Widgets;

use App\Models\ClinicExpense;
use App\Models\Invoice;
use App\Services\CurrencyHelper;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class AdminRevenueExpenseChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue vs Expenses (Last 6 Months)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    protected function getData(): array
    {
        $revenueData = [];
        $expenseData = [];
        $labels = [];

        // Get the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            
            $revenue = Invoice::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('paid_amount');

            $expense = ClinicExpense::whereYear('expense_date', $month->year)
                ->whereMonth('expense_date', $month->month)
                ->sum('amount');

            $revenueData[] = $revenue;
            $expenseData[] = $expense;
            $labels[] = $month->format('M Y');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collected Revenue (' . CurrencyHelper::symbol() . ')',
                    'data' => $revenueData,
                    'backgroundColor' => '#10b981', // Emerald 500
                    'borderColor' => '#059669', // Emerald 600
                ],
                [
                    'label' => 'Clinic Expenses (' . CurrencyHelper::symbol() . ')',
                    'data' => $expenseData,
                    'backgroundColor' => '#ef4444', // Red 500
                    'borderColor' => '#dc2626', // Red 600
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

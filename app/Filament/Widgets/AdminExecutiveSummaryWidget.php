<?php

namespace App\Filament\Widgets;

use App\Models\ClinicExpense;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminExecutiveSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    protected function getStats(): array
    {
        $thisMonth = Carbon::now()->startOfMonth();

        // 1. Total Revenue (MTD)
        $monthlyRevenue = Invoice::where('created_at', '>=', $thisMonth)->sum('paid_amount');

        // 2. Total Expenses (MTD)
        $monthlyExpenses = ClinicExpense::where('expense_date', '>=', $thisMonth)->sum('amount');

        // 3. Net Profit
        $netProfit = $monthlyRevenue - $monthlyExpenses;

        // 4. Outstanding Debt
        $outstandingDebt = Invoice::whereIn('status', ['unpaid', 'partially_paid', 'overdue'])->sum('remaining_balance');

        return [
            Stat::make('MTD Revenue', number_format($monthlyRevenue, 2) . ' EGP')
                ->description('Month to Date Revenue')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->url(route('filament.admin.resources.invoices.index')),

            Stat::make('MTD Expenses', number_format($monthlyExpenses, 2) . ' EGP')
                ->description('Month to Date Expenses')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->url(route('filament.admin.resources.clinic-expenses.index')),

            Stat::make('Net Profit', number_format($netProfit, 2) . ' EGP')
                ->description('Revenue minus Expenses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Outstanding Patient Debt', number_format($outstandingDebt, 2) . ' EGP')
                ->description('Unpaid balances from patients')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning')
                ->url(route('filament.admin.resources.invoices.index', ['tableFilters[status][value]' => 'unpaid'])),
        ];
    }
}

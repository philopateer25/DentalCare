<?php

namespace App\Filament\Widgets;

use App\Models\ClinicExpense;
use App\Models\Invoice;
use App\Services\CurrencyHelper;
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
            Stat::make('MTD Revenue', CurrencyHelper::format($monthlyRevenue))
                ->description('Month to Date Revenue')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index')),

            Stat::make('MTD Expenses', CurrencyHelper::format($monthlyExpenses))
                ->description('Month to Date Expenses')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->url(\App\Filament\Resources\ClinicExpenseResource::getUrl('index')),

            Stat::make('Net Profit', CurrencyHelper::format($netProfit))
                ->description('Revenue minus Expenses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Outstanding Patient Debt', CurrencyHelper::format($outstandingDebt))
                ->description('Unpaid balances from patients')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index', ['tableFilters[status][value]' => 'unpaid'])),
        ];
    }
}

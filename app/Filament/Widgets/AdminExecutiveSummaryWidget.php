<?php

namespace App\Filament\Widgets;

use App\Models\ClinicExpense;
use App\Models\Invoice;
use App\Models\Payment;
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
        $practiceId = \Filament\Facades\Filament::getTenant()?->id;

        // 1. Gross Production (MTD)
        $grossProduction = Invoice::where('practice_id', $practiceId)->whereDate('issue_date', '>=', $thisMonth)->sum('total_amount');

        // 2. Total Revenue / Collections (MTD)
        $monthlyRevenue = Payment::where('practice_id', $practiceId)->whereDate('paid_at', '>=', $thisMonth)->sum('amount');

        // 3. Total Expenses (MTD)
        $monthlyExpenses = ClinicExpense::where('practice_id', $practiceId)->whereDate('expense_date', '>=', $thisMonth)->sum('amount');

        // 4. Net Profit
        $netProfit = $monthlyRevenue - $monthlyExpenses;

        // 5. Outstanding AR Balance (Patient Debt)
        $outstandingDebt = Invoice::where('practice_id', $practiceId)->where('balance_due', '>', 0)->sum('balance_due');

        return [
            Stat::make('Gross Production', CurrencyHelper::format($grossProduction))
                ->description('Total value of procedures this month')
                ->descriptionIcon('heroicon-m-document-chart-bar')
                ->color('primary')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index')),

            Stat::make('MTD Collections (Revenue)', CurrencyHelper::format($monthlyRevenue))
                ->description('Actual payments received this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(\App\Filament\Resources\PaymentResource::getUrl('index', ['tableFilters[mtd][isActive]' => 1])),

            Stat::make('MTD Expenses', CurrencyHelper::format($monthlyExpenses))
                ->description('Operating expenses this month')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->url(\App\Filament\Resources\ClinicExpenseResource::getUrl('index', ['tableFilters[mtd][isActive]' => 1])),

            Stat::make('Net Profit (MTD)', CurrencyHelper::format($netProfit))
                ->description('Collections minus Expenses')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Outstanding AR Balance', CurrencyHelper::format($outstandingDebt))
                ->description('Total unpaid patient invoices')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index', ['tableFilters[unsettled][isActive]' => 1])),
        ];
    }
}

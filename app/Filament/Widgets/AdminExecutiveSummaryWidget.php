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
        return auth()->user()?->hasAnyRole(['clinic_admin', 'super_admin', 'developer']) ?? false;
    }

    protected function getStats(): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        $financeService = app(\App\Services\Widgets\FinanceWidgetService::class);

        // 1. Gross Production (MTD)
        $grossProduction = Invoice::where('practice_id', $practiceId)->whereDate('issue_date', '>=', $thisMonth)->sum('total_amount');

        // 2. Total Revenue / Collections (MTD)
        $monthlyRevenue = Payment::where('practice_id', $practiceId)->whereDate('paid_at', '>=', $thisMonth)->sum('amount');

        // 3. Separate Expense Streams (MTD)
        $monthlyClinicExpenses = $financeService->getMonthlyClinicExpenses($practiceId, Carbon::now());
        $monthlyPayroll = $financeService->getMonthlyPayrollExpenses($practiceId, Carbon::now());
        $monthlyCommissions = $financeService->getMonthlyCommissionExpenses($practiceId, Carbon::now());
        $totalMonthlyExpenses = $monthlyClinicExpenses + $monthlyPayroll + $monthlyCommissions;

        // 4. Net Profit
        $netProfit = $monthlyRevenue - $totalMonthlyExpenses;

        // 5. Outstanding AR Balance (Patient Debt)
        $outstandingDebt = Invoice::where('practice_id', $practiceId)->where('balance_due', '>', 0)->sum('balance_due');

        return [
            Stat::make('Gross Production', CurrencyHelper::format($grossProduction))
                ->description('Clinical procedures billed this month')
                ->descriptionIcon('heroicon-m-document-chart-bar')
                ->color('primary')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index')),

            Stat::make('MTD Collections (Revenue)', CurrencyHelper::format($monthlyRevenue))
                ->description('Actual payments collected this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(\App\Filament\Resources\PaymentResource::getUrl('index', ['tableFilters[mtd][isActive]' => 1])),

            Stat::make('Net Profit (MTD)', CurrencyHelper::format($netProfit))
                ->description('Collections minus all expenses')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->url(\App\Filament\Resources\PaymentResource::getUrl('index')),

            Stat::make('Outstanding AR Balance', CurrencyHelper::format($outstandingDebt))
                ->description('Unpaid patient invoices')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index', ['tableFilters[unsettled][isActive]' => 1])),

            Stat::make('Clinic Overhead Expenses', CurrencyHelper::format($monthlyClinicExpenses))
                ->description('Rent, supplies, utilities & lab costs')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('danger')
                ->url(\App\Filament\Resources\ClinicExpenseResource::getUrl('index', ['tableFilters[mtd][isActive]' => 1])),

            Stat::make('Staff & Doctor Salaries', CurrencyHelper::format($monthlyPayroll))
                ->description('Disbursed monthly payroll slips')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('danger')
                ->url(\App\Filament\Resources\PayrollSlipResource::getUrl('index')),

            Stat::make('Doctor Commission Payouts', CurrencyHelper::format($monthlyCommissions))
                ->description('Direct settled procedure splits')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('danger')
                ->url(\App\Filament\Resources\DoctorCommissionResource::getUrl('index')),
        ];
    }
}

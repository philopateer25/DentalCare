<?php

namespace App\Filament\Widgets;

use App\Services\CurrencyHelper;
use App\Services\Widgets\FinanceWidgetService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $practiceId = Filament::getTenant()?->id ?? auth()->user()?->practice_id ?? 1;
        $service = app(FinanceWidgetService::class);

        $grossProduction = $service->getGrossProduction($practiceId);
        $collections = $service->getCollectionsThisMonth($practiceId);
        $outstanding = $service->getOutstandingBalance($practiceId);

        return [
            Stat::make('Gross Production', CurrencyHelper::format($grossProduction))
                ->description('Sum of all issued procedure invoices')
                ->icon('heroicon-o-document-chart-bar')
                ->color('primary'),

            Stat::make('Collections This Month', CurrencyHelper::format($collections))
                ->description('Collected revenue in current month')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Outstanding AR Balance', CurrencyHelper::format($outstanding))
                ->description('Unpaid balance due on active invoices')
                ->icon('heroicon-o-exclamation-circle')
                ->color($outstanding > 0 ? 'warning' : 'success'),
        ];
    }
}

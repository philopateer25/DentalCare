<?php

namespace App\Filament\Widgets;

use App\Services\CurrencyHelper;
use App\Services\Widgets\InventoryWidgetService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $practiceId = Filament::getTenant()?->id ?? auth()->user()?->practice_id ?? 1;
        $service = app(InventoryWidgetService::class);

        $valuation = $service->getInventoryValuation($practiceId);
        $lowStock = $service->getLowStock($practiceId);
        $expiring = $service->getExpiringSoon($practiceId, 60);
        $consumption = $service->getConsumptionRate($practiceId, 30);

        return [
            Stat::make('Total Inventory Valuation', CurrencyHelper::format($valuation))
                ->description('Current remaining stock value (FEFO/FIFO)')
                ->icon('heroicon-o-archive-box')
                ->color('success'),

            Stat::make('Low Stock Items', number_format($lowStock['count']))
                ->description($lowStock['count'] > 0 ? 'Items below reorder level' : 'Stock levels optimal')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($lowStock['count'] > 0 ? 'danger' : 'success'),

            Stat::make('Expiring Soon (60 Days)', number_format($expiring['count']))
                ->description($expiring['count'] > 0 ? 'Batches require attention' : 'All active batches valid')
                ->icon('heroicon-o-clock')
                ->color($expiring['count'] > 0 ? 'warning' : 'success'),

            Stat::make('30-Day Stock Consumption', number_format($consumption['total_consumed']) . ' units')
                ->description("Avg {$consumption['daily_consumption_rate']} units/day")
                ->icon('heroicon-o-arrow-trending-down')
                ->color('info'),
        ];
    }
}

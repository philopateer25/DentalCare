<?php

namespace App\Filament\Resources\InventoryResource\Widgets;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalItems = InventoryItem::count();

        $totalValuation = InventoryBatch::query()
            ->selectRaw('SUM(unit_cost * quantity_remaining) as total_val')
            ->value('total_val') ?? 0;

        $expiringCount = InventoryBatch::query()
            ->whereNotNull('expiry_date')
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '<=', now()->addDays(60))
            ->count();

        $outOfStockCount = InventoryItem::query()
            ->where(function ($query) {
                $query->whereDoesntHave('batches')
                    ->orWhereRaw('(SELECT COALESCE(SUM(quantity_remaining), 0) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id) <= inventory_items.min_reorder_level');
            })->count();

        return [
            Stat::make('Total Catalog Tools & Items', number_format($totalItems))
                ->description('Active clinic supplies & instruments')
                ->icon('heroicon-o-archive-box')
                ->color('primary'),

            Stat::make('Total Inventory Valuation', '$' . number_format($totalValuation, 2))
                ->description('Current remaining stock value')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Expiry Alerts (Next 60 Days)', number_format($expiringCount))
                ->description($expiringCount > 0 ? 'Batches need attention' : 'All batches valid')
                ->icon('heroicon-o-clock')
                ->color($expiringCount > 0 ? 'warning' : 'success'),

            Stat::make('Out of Stock / Restock Needed', number_format($outOfStockCount))
                ->description($outOfStockCount > 0 ? 'Items below reorder level' : 'Stock levels optimal')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}

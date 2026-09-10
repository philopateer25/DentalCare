<?php

namespace App\Services\Widgets;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\ProcedureConsumption;

class InventoryWidgetService
{
    /**
     * Get Low Stock item count and list for practice.
     */
    public function getLowStock(int $practiceId): array
    {
        $items = InventoryItem::where('practice_id', $practiceId)
            ->where(function ($query) {
                $query->whereDoesntHave('batches')
                    ->orWhereRaw('(SELECT COALESCE(SUM(quantity_remaining), 0) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id) <= inventory_items.min_reorder_level');
            })
            ->get();

        return [
            'count' => $items->count(),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'current_stock' => $item->total_stock,
                'min_reorder_level' => $item->min_reorder_level,
                'unit' => $item->unit,
            ])->toArray(),
        ];
    }

    /**
     * Get Expiring Soon batches count and list for practice (default window 60 days).
     */
    public function getExpiringSoon(int $practiceId, int $days = 60): array
    {
        $batches = InventoryBatch::whereHas('inventoryItem', function ($q) use ($practiceId) {
            $q->where('practice_id', $practiceId);
        })
        ->whereNotNull('expiry_date')
        ->where('quantity_remaining', '>', 0)
        ->where('expiry_date', '<=', now()->addDays($days))
        ->orderBy('expiry_date', 'asc')
        ->get();

        return [
            'count' => $batches->count(),
            'batches' => $batches->map(fn ($b) => [
                'id' => $b->id,
                'item_name' => $b->inventoryItem?->name ?? 'Item',
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->format('Y-m-d'),
                'days_remaining' => (int) now()->diffInDays($b->expiry_date, false),
                'quantity_remaining' => $b->quantity_remaining,
            ])->toArray(),
        ];
    }

    /**
     * Get Consumption Rate over recent days (default 30 days) for practice.
     */
    public function getConsumptionRate(int $practiceId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $consumptions = ProcedureConsumption::whereHas('inventoryBatch.inventoryItem', function ($q) use ($practiceId) {
            $q->where('practice_id', $practiceId);
        })
        ->where('created_at', '>=', $since)
        ->get();

        $totalUnitsConsumed = (int) $consumptions->sum('quantity_consumed');
        $dailyRate = $days > 0 ? round($totalUnitsConsumed / $days, 2) : 0;

        return [
            'days_window' => $days,
            'total_consumed' => $totalUnitsConsumed,
            'daily_consumption_rate' => $dailyRate,
        ];
    }

    /**
     * Get Total Inventory Valuation for practice (FEFO/FIFO costing: sum of unit_cost * quantity_remaining).
     */
    public function getInventoryValuation(int $practiceId): float
    {
        return (float) (InventoryBatch::whereHas('inventoryItem', function ($q) use ($practiceId) {
            $q->where('practice_id', $practiceId);
        })
        ->selectRaw('SUM(unit_cost * quantity_remaining) as val')
        ->value('val') ?? 0.00);
    }
}

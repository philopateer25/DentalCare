<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    /**
     * Adjust stock for an inventory item.
     *
     * @param InventoryItem $item
     * @param int $quantity Must be greater than 0
     * @param string $type 'addition'|'subtraction'|'purchase'|'waste'|'expired'|'adjustment'
     * @param string|null $reason
     * @param User|null $user
     * @param InventoryBatch|null $batch
     * @param float|null $unitCost
     * @return InventoryStockMovement
     */
    public function adjustStock(
        InventoryItem $item,
        int $quantity,
        string $type,
        ?string $reason = null,
        ?User $user = null,
        ?InventoryBatch $batch = null,
        ?float $unitCost = null
    ): InventoryStockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Adjustment quantity must be greater than zero.');
        }

        $user = $user ?? auth()->user();

        // Enforce practice tenant isolation
        if ($user && !$user->hasRole('developer') && (int) $user->practice_id !== (int) $item->practice_id) {
            throw new InvalidArgumentException('Cross-practice inventory adjustment is prohibited.');
        }

        // Enforce server-side policy authorization
        if ($user && !$user->can('adjustStock', $item)) {
            throw new InvalidArgumentException('Unauthorized user attempted inventory stock adjustment.');
        }

        return DB::transaction(function () use ($item, $quantity, $type, $reason, $user, $batch, $unitCost) {
            $isIncrease = in_array($type, ['addition', 'purchase']);
            $previousQuantity = $item->total_stock;

            if ($isIncrease) {
                if ($batch) {
                    if ($batch->inventory_item_id !== $item->id) {
                        throw new InvalidArgumentException('Batch does not belong to this inventory item.');
                    }
                    $batch->increment('quantity_remaining', $quantity);
                    if ($unitCost !== null && $unitCost > 0) {
                        $batch->update(['unit_cost' => $unitCost]);
                    }
                    $targetBatch = $batch;
                } else {
                    $effectiveCost = $unitCost ?? $item->unit_price ?? 0.00;
                    $targetBatch = InventoryBatch::create([
                        'inventory_item_id' => $item->id,
                        'supplier_id' => $item->supplier_id,
                        'batch_number' => 'BAT-' . date('Ymd') . '-' . str_pad((string) (InventoryBatch::max('id') + 1), 4, '0', STR_PAD_LEFT),
                        'received_date' => now(),
                        'unit_cost' => $effectiveCost,
                        'quantity_received' => $quantity,
                        'quantity_remaining' => $quantity,
                        'notes' => $reason,
                    ]);
                }

                $resultingQuantity = $previousQuantity + $quantity;
                $effectiveUnitCost = $unitCost ?? $targetBatch->unit_cost;
                $quantityChange = $quantity;
            } else {
                // Stock decrease
                if ($previousQuantity < $quantity) {
                    throw new InvalidArgumentException("Insufficient inventory stock. Total available: {$previousQuantity}, requested reduction: {$quantity}.");
                }

                if ($batch) {
                    if ($batch->inventory_item_id !== $item->id) {
                        throw new InvalidArgumentException('Batch does not belong to this inventory item.');
                    }
                    if ($batch->quantity_remaining < $quantity) {
                        throw new InvalidArgumentException("Insufficient batch stock. Available: {$batch->quantity_remaining}, requested reduction: {$quantity}.");
                    }
                    $batch->decrement('quantity_remaining', $quantity);
                    $targetBatch = $batch;
                    $effectiveUnitCost = $batch->unit_cost;
                } else {
                    // FIFO depletion across active batches
                    $remainingToDeplete = $quantity;
                    $batches = $item->batches()
                        ->where('quantity_remaining', '>', 0)
                        ->orderByRaw('expiry_date IS NULL ASC')
                        ->orderBy('expiry_date', 'asc')
                        ->orderBy('received_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();

                    $lastDepletedBatch = null;
                    $totalDepletedCost = 0.0;

                    foreach ($batches as $b) {
                        if ($remainingToDeplete <= 0) {
                            break;
                        }
                        $take = min($b->quantity_remaining, $remainingToDeplete);
                        $b->decrement('quantity_remaining', $take);
                        $remainingToDeplete -= $take;
                        $totalDepletedCost += ($take * (float) $b->unit_cost);
                        $lastDepletedBatch = $b;
                    }

                    $targetBatch = $lastDepletedBatch;
                    $effectiveUnitCost = $quantity > 0 ? round($totalDepletedCost / $quantity, 2) : 0.00;
                }

                $resultingQuantity = $previousQuantity - $quantity;
                $quantityChange = -$quantity;
            }

            return InventoryStockMovement::create([
                'practice_id' => $item->practice_id,
                'inventory_item_id' => $item->id,
                'inventory_batch_id' => $targetBatch?->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'previous_quantity' => $previousQuantity,
                'resulting_quantity' => $resultingQuantity,
                'unit_cost' => $effectiveUnitCost,
                'reason' => $reason,
            ]);
        });
    }
}

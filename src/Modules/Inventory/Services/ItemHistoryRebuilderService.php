<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryStockMovement;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;

class ItemHistoryRebuilderService
{
    /**
     * Mathematically recalculate and reconstruct the running inventory balance
     * for a given location and product.
     */
    public function rebuildLocationHistory(int $locationId, ?int $productId = null): array
    {
        $location = Location::findOrFail($locationId);

        return DB::transaction(function () use ($location, $productId) {
            $productIds = $productId
                ? [$productId]
                : ProductInventory::where(function ($q) use ($location) {
                    $q->where('location_id', $location->id)
                        ->orWhere('outlet_id', $location->id);
                })
                    ->pluck('product_id')
                    ->unique()
                    ->toArray();

            $rebuiltCount = 0;
            $totalMovements = 0;

            foreach ($productIds as $pId) {
                $movements = InventoryStockMovement::where(function ($q) use ($location) {
                    $q->where('location_id', $location->id)
                        ->orWhere('outlet_id', $location->id);
                })
                    ->where('product_id', $pId)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                $runningBalance = 0.0;
                foreach ($movements as $mvt) {
                    $runningBalance += (float) $mvt->quantity_change;
                    $mvt->update(['running_balance' => $runningBalance]);
                    $totalMovements++;
                }

                // Update authoritative balance on ProductInventory
                $inventory = ProductInventory::where('product_id', $pId)
                    ->where(function ($q) use ($location) {
                        $q->where('location_id', $location->id)
                            ->orWhere('outlet_id', $location->id);
                    })
                    ->first();

                if ($inventory) {
                    $inventory->update([
                        'current_stock' => $runningBalance,
                        'available_quantity' => $runningBalance,
                    ]);
                }

                $rebuiltCount++;
            }

            return [
                'location_id' => $location->id,
                'products_rebuilt' => $rebuiltCount,
                'movements_processed' => $totalMovements,
            ];
        });
    }
}

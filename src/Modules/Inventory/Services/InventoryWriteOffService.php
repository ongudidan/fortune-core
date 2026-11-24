<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryWriteOff;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryWriteOffBatch;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;

class InventoryWriteOffService
{
    public function __construct(protected StockMovementService $stockMovementService) {}

    /**
     * Record inventory write-off (spoilage, breakage, expiry) with automated General Ledger postings.
     *
     * @param  array  $items  Array of ['product_id' => int, 'quantity' => float, 'unit_cost' => ?float, 'batch_id' => ?int]
     */
    public function createWriteOff(
        int $locationId,
        array $items,
        string $reason = 'spoilage',
        ?string $notes = null,
        ?int $userId = null
    ): InventoryWriteOff {
        return DB::transaction(function () use ($locationId, $items, $reason, $userId) {
            $referenceNo = 'WO-'.strtoupper(substr(uniqid(), -6));
            $totalLossValue = 0.0;

            $writeOff = InventoryWriteOff::create([
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'reference_no' => $referenceNo,
                'total_loss_value' => 0.00,
                'status' => 'completed',
                'reason' => $reason,
                'requested_by' => $userId ?: auth()->id(),
                'approved_by' => $userId ?: auth()->id(),
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $product = Product::findOrFail($productId);
                $unitCost = isset($item['unit_cost']) && (float) $item['unit_cost'] > 0
                    ? (float) $item['unit_cost']
                    : (float) $product->cost_price;

                $lineTotal = round($qty * $unitCost, 2);
                $totalLossValue += $lineTotal;

                InventoryWriteOffBatch::create([
                    'inventory_write_off_id' => $writeOff->id,
                    'product_id' => $productId,
                    'batch_id' => $item['batch_id'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                ]);

                // Apply stock reduction (triggers GL: Debit 5500 Shrinkage/Loss, Credit 1300 Inventory Asset)
                $this->stockMovementService->applyAdjustment(
                    productId: $productId,
                    quantityChange: -$qty,
                    locationId: $locationId,
                    movementType: 'write_off',
                    referenceType: 'WRITE_OFF',
                    referenceId: $writeOff->id,
                    notes: "Write-off ({$reason}) for {$product->name}",
                    userId: $userId,
                    customUnitCost: $unitCost
                );
            }

            $writeOff->update(['total_loss_value' => $totalLossValue]);

            return $writeOff->load('batches.product');
        });
    }
}

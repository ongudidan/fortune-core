<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryBatch;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryCostAdjustment;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryCostMovement;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;

class CostAdjustmentService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? (class_exists(LedgerService::class) ? app(LedgerService::class) : null);
    }

    /**
     * Apply a valuation cost adjustment to a product's inventory on hand.
     * Recalculates average_cost, updates active batches, records an immutable
     * cost movement audit, and posts balanced double-entry General Ledger lines.
     */
    public function applyCostAdjustment(
        int $productId,
        float $newCost,
        int $locationId,
        ?int $unitId = null,
        float $factor = 1.0,
        string $reason = '',
        ?int $userId = null
    ): InventoryCostAdjustment {
        return DB::transaction(function () use (
            $productId,
            $newCost,
            $locationId,
            $unitId,
            $factor,
            $reason,
            $userId
        ) {
            $product = Product::findOrFail($productId);
            $factor = $factor > 0 ? (float) $factor : 1.0;
            $newBaseCost = round($newCost / $factor, 6);
            $userId = $userId ?: auth()->id();

            $inventory = ProductInventory::where('product_id', $productId)
                ->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                })
                ->lockForUpdate()
                ->first();

            $currentStock = $inventory ? (float) $inventory->available_quantity : 0.0;
            $oldBaseCost = $inventory && (float) $inventory->average_cost > 0
                ? (float) $inventory->average_cost
                : (float) $product->cost_price;

            $costDifference = round($newBaseCost - $oldBaseCost, 6);
            $totalValueImpact = round($costDifference * $currentStock, 2);

            // Update ProductInventory valuation
            if ($inventory) {
                $inventory->update([
                    'average_cost' => $newBaseCost,
                    'base_cost' => $newBaseCost,
                ]);
            }

            // Update open batches for this product & location
            InventoryBatch::where('product_id', $productId)
                ->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                })
                ->where('is_active', true)
                ->update(['cost_price' => $newBaseCost]);

            // Create InventoryCostAdjustment record
            $adjustment = InventoryCostAdjustment::create([
                'location_id' => $locationId,
                'product_id' => $productId,
                'unit_id' => $unitId ?: $product->unit_id,
                'factor' => $factor,
                'old_unit_cost' => $oldBaseCost,
                'new_unit_cost' => $newBaseCost,
                'current_stock' => $currentStock,
                'cost_difference' => $costDifference,
                'total_value_impact' => $totalValueImpact,
                'reason' => $reason ?: "Valuation revaluation for {$product->name}",
                'status' => 'Approved',
                'user_id' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Record Cost Movement audit entry
            InventoryCostMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'unit_cost' => $newBaseCost,
                'total_cost' => $totalValueImpact,
                'cost_type' => 'cost_adjustment',
                'reference_type' => 'COST_ADJ',
                'reference_id' => (string) $adjustment->id,
            ]);

            // Double-Entry General Ledger Posting
            if (config('fortune.modules.ledger', true) && $this->ledgerService && abs($totalValueImpact) > 0.001) {
                $assetAccount = config('fortune.inventory.accounts.inventory_asset', '1300');
                $gainLossAccount = config('fortune.inventory.accounts.shrinkage_expense', '5500');
                $absVal = abs($totalValueImpact);

                if ($totalValueImpact > 0) {
                    // Gain: Debit Inventory Asset 1300, Credit Adjustment Gain 5500
                    $this->ledgerService->postJournal([
                        [
                            'account_code' => $assetAccount,
                            'debit' => $absVal,
                            'credit' => 0.00,
                            'entry_type' => 'CostAdjustmentGain',
                            'reference' => "CADJ-{$adjustment->id}",
                            'description' => "Inventory cost adjustment gain: {$product->name}",
                        ],
                        [
                            'account_code' => $gainLossAccount,
                            'debit' => 0.00,
                            'credit' => $absVal,
                            'entry_type' => 'CostAdjustmentGain',
                            'reference' => "CADJ-{$adjustment->id}",
                            'description' => "Inventory revaluation gain: {$product->name}",
                        ],
                    ]);
                } else {
                    // Loss: Debit Revaluation Loss 5500, Credit Inventory Asset 1300
                    $this->ledgerService->postJournal([
                        [
                            'account_code' => $gainLossAccount,
                            'debit' => $absVal,
                            'credit' => 0.00,
                            'entry_type' => 'CostAdjustmentLoss',
                            'reference' => "CADJ-{$adjustment->id}",
                            'description' => "Inventory cost adjustment loss: {$product->name}",
                        ],
                        [
                            'account_code' => $assetAccount,
                            'debit' => 0.00,
                            'credit' => $absVal,
                            'entry_type' => 'CostAdjustmentLoss',
                            'reference' => "CADJ-{$adjustment->id}",
                            'description' => "Inventory asset write-down: {$product->name}",
                        ],
                    ]);
                }
            }

            return $adjustment->load(['product', 'location']);
        });
    }
}

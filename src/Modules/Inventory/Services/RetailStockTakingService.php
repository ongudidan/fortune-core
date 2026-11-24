<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryStockAdjustment;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\StockTaking;

class RetailStockTakingService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected StockMovementService $stockMovementService
    ) {}

    /**
     * Normalize counted physical packages and loose units to the product's base unit.
     */
    public function normalizeCounts(int $productId, float $packages, float $looseUnits, ?int $packUnitId = null): float
    {
        $factor = 1.0;
        if ($packUnitId) {
            $factor = $this->inventoryService->getUnitConversionFactor($productId, $packUnitId);
        }

        return round(($packages * $factor) + $looseUnits, 4);
    }

    /**
     * Finalize an inventory stock taking session:
     * Computes final variances, records stock adjustments, and automatically
     * triggers balanced double-entry General Ledger postings (1300 Inventory Asset & 5500 Shrinkage/Gain).
     */
    public function finalizeStockTaking(int $stockTakingId, ?int $approverId = null): StockTaking
    {
        return DB::transaction(function () use ($stockTakingId, $approverId) {
            $stockTaking = StockTaking::with(['items.product'])->findOrFail($stockTakingId);

            $totalVarianceValue = 0.0;
            $locationId = (int) ($stockTaking->location_id ?: $stockTaking->outlet_id);

            foreach ($stockTaking->items as $item) {
                $bookQty = (float) $item->book_quantity;
                $physQty = (float) $item->physical_quantity;
                $variance = round($physQty - $bookQty, 4);
                $unitCost = (float) ($item->cost_price ?: ($item->product?->cost_price ?: 0.0));
                $varValue = round($variance * $unitCost, 2);

                $item->update([
                    'variance_quantity' => $variance,
                    'variance_value' => $varValue,
                ]);

                $totalVarianceValue += $varValue;

                if (abs($variance) > 0.0001) {
                    $adjType = $variance >= 0 ? 'addition' : 'deduction';

                    // 1. Record Inventory Stock Adjustment
                    $adjustment = InventoryStockAdjustment::create([
                        'stock_taking_id' => $stockTaking->id,
                        'location_id' => $locationId,
                        'outlet_id' => $locationId,
                        'product_id' => $item->product_id,
                        'quantity' => abs($variance),
                        'type' => $adjType,
                        'unit_cost' => $unitCost,
                        'total_value' => abs($varValue),
                        'reason' => "Stock taking variance: {$adjType} of {$variance} units",
                        'approved_by' => $approverId ?: auth()->id(),
                    ]);

                    // 2. Apply Stock Movement & GL Posting
                    $this->stockMovementService->applyAdjustment(
                        productId: $item->product_id,
                        quantityChange: $variance,
                        locationId: $locationId,
                        movementType: $adjType,
                        referenceType: 'STOCK_TAKING',
                        referenceId: $stockTaking->id,
                        notes: "Stock Taking #{$stockTaking->id} adjustment ({$adjType})",
                        userId: $approverId,
                        customUnitCost: $unitCost
                    );
                }
            }

            $stockTaking->update([
                'status' => 'completed',
                'approved_by' => $approverId ?: auth()->id(),
                'total_variance_value' => $totalVarianceValue,
            ]);

            return $stockTaking->load(['items.product', 'adjustments']);
        });
    }

    /**
     * Transform a Product model into the Retail Stock Taking UI format with packaging options.
     */
    public function transformProductToAuditItem(Product $product, ?int $locationId = null): array
    {
        $bookStock = $locationId ? $this->stockMovementService->getLocationStock($product->id, $locationId) : 0.0;
        $conversions = $product->conversions()->with('unit')->get();

        $units = [];
        if ($product->unit) {
            $units[] = [
                'unit_id' => $product->unit->id,
                'name' => $product->unit->name,
                'factor' => 1.0,
                'is_base' => true,
            ];
        }

        foreach ($conversions as $conv) {
            if ($conv->unit) {
                $units[] = [
                    'unit_id' => $conv->unit->id,
                    'name' => $conv->unit->name,
                    'factor' => (float) $conv->factor,
                    'is_base' => false,
                ];
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'cost_price' => (float) $product->cost_price,
            'selling_price' => (float) $product->selling_price,
            'book_stock' => $bookStock,
            'available_units' => $units,
        ];
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryBatch;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductUnitConversion;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductVariation;

class InventoryService
{
    /**
     * Convert a quantity from a given unit to the product's base unit.
     */
    public function convertToBase(int $productId, float $qty, ?int $unitId = null): float
    {
        if (! $unitId) {
            return (float) $qty;
        }

        $product = Product::findOrFail($productId);
        $baseUnitId = $product->base_unit_id ?: $product->unit_id;

        if ($baseUnitId && (int) $baseUnitId === (int) $unitId) {
            return (float) $qty;
        }

        $conversion = ProductUnitConversion::where('product_id', $productId)
            ->where(function ($q) use ($unitId) {
                $q->where('unit_id', $unitId)
                    ->orWhere('from_unit_id', $unitId);
            })
            ->first();

        if ($conversion && (float) $conversion->factor > 0) {
            $operator = $conversion->operator ?: '*';
            $factor = (float) $conversion->factor;

            return $operator === '/' ? ($qty / $factor) : ($qty * $factor);
        }

        // Fallback: check variations
        $variation = ProductVariation::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($variation && (float) $variation->conversion_factor > 0) {
            return (float) ($qty * $variation->conversion_factor);
        }

        return (float) $qty;
    }

    /**
     * Get conversion factor between a unit and product base unit.
     */
    public function getUnitConversionFactor(int $productId, ?int $unitId): float
    {
        if (! $unitId) {
            return 1.0;
        }

        $product = Product::find($productId);
        $baseUnitId = $product?->base_unit_id ?: $product?->unit_id;

        if ($baseUnitId && (int) $baseUnitId === (int) $unitId) {
            return 1.0;
        }

        $conversion = ProductUnitConversion::where('product_id', $productId)
            ->where(function ($q) use ($unitId) {
                $q->where('unit_id', $unitId)
                    ->orWhere('from_unit_id', $unitId);
            })
            ->first();

        if ($conversion && (float) $conversion->factor > 0) {
            return (float) $conversion->factor;
        }

        $variation = ProductVariation::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($variation && (float) $variation->conversion_factor > 0) {
            return (float) $variation->conversion_factor;
        }

        return 1.0;
    }

    /**
     * Calculate how many empty containers are yielded by a sale.
     */
    public function getEmptyYield(int $saleProductId, float $qty, ?int $saleUnitId, int $emptyProductId): float
    {
        $saleProduct = Product::find($saleProductId);
        $emptyProduct = Product::find($emptyProductId);

        if (! $saleProduct || ! $emptyProduct) {
            return 0.0;
        }

        $baseQtySold = $this->convertToBase($saleProductId, $qty, $saleUnitId);

        // Check if a direct variation defines empty container mapping
        $variation = ProductVariation::where('product_id', $saleProductId)
            ->where(function ($q) use ($saleUnitId) {
                if ($saleUnitId) {
                    $q->where('unit_id', $saleUnitId);
                }
            })
            ->where('empty_product_id', $emptyProductId)
            ->first();

        if ($variation && (float) $variation->conversion_factor > 0) {
            return (float) ($baseQtySold / $variation->conversion_factor);
        }

        // Direct container mapping on Product
        if ($saleProduct->container_product_id === $emptyProductId) {
            $factor = $this->getUnitConversionFactor($saleProductId, $saleUnitId);

            return (float) ($baseQtySold / ($factor > 0 ? $factor : 1.0));
        }

        return (float) $baseQtySold;
    }

    /**
     * Increase physical stock for a product at a location.
     */
    public function increaseStock(int $productId, float $qty, int $locationId, ?int $unitId = null): ProductInventory
    {
        $baseQty = $this->convertToBase($productId, $qty, $unitId);

        return DB::transaction(function () use ($productId, $baseQty, $locationId) {
            $location = Location::find($locationId);

            $inventory = ProductInventory::firstOrCreate(
                [
                    'product_id' => $productId,
                    'location_id' => $locationId,
                ],
                [
                    'outlet_id' => $locationId,
                    'company_id' => $location?->company_id,
                    'branch_id' => $location?->branch_id,
                    'current_stock' => 0,
                    'available_quantity' => 0,
                ]
            );

            $newQty = (float) $inventory->available_quantity + $baseQty;
            $inventory->update([
                'current_stock' => $newQty,
                'available_quantity' => $newQty,
            ]);

            return $inventory;
        });
    }

    /**
     * Decrease physical stock for a product at a location.
     */
    public function decreaseStock(int $productId, float $qty, int $locationId, ?int $unitId = null): ProductInventory
    {
        $baseQty = $this->convertToBase($productId, $qty, $unitId);

        return DB::transaction(function () use ($productId, $baseQty, $locationId) {
            $location = Location::find($locationId);

            $inventory = ProductInventory::firstOrCreate(
                [
                    'product_id' => $productId,
                    'location_id' => $locationId,
                ],
                [
                    'outlet_id' => $locationId,
                    'company_id' => $location?->company_id,
                    'branch_id' => $location?->branch_id,
                    'current_stock' => 0,
                    'available_quantity' => 0,
                ]
            );

            $newQty = max(0, (float) $inventory->available_quantity - $baseQty);
            $inventory->update([
                'current_stock' => $newQty,
                'available_quantity' => $newQty,
            ]);

            return $inventory;
        });
    }

    /**
     * Adjust FIFO/AVCO batches for a product at a location.
     */
    public function adjustBatches(int $productId, int $locationId, float $quantityChange, ?float $costPrice = null): void
    {
        if (abs($quantityChange) < 0.0001) {
            return;
        }

        if ($quantityChange < 0) {
            // Deficit: Consume batches FIFO
            $deficit = abs($quantityChange);
            $batches = InventoryBatch::where('product_id', $productId)
                ->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                })
                ->where('quantity_available', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($batches as $batch) {
                if ($deficit <= 0) {
                    break;
                }
                $consume = min((float) $batch->quantity_available, $deficit);
                $newAvailable = (float) $batch->quantity_available - $consume;
                $batch->update([
                    'quantity_available' => $newAvailable,
                    'current_quantity' => $newAvailable,
                ]);
                $deficit -= $consume;
            }
        } else {
            // Surplus: Create or top up latest active batch
            $latestBatch = InventoryBatch::where('product_id', $productId)
                ->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                })
                ->where('is_active', true)
                ->latest('id')
                ->first();

            if ($latestBatch) {
                $newAvailable = (float) $latestBatch->quantity_available + $quantityChange;
                $latestBatch->update([
                    'quantity_available' => $newAvailable,
                    'current_quantity' => $newAvailable,
                ]);
            } else {
                InventoryBatch::create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'outlet_id' => $locationId,
                    'batch_number' => 'BAT-'.strtoupper(substr(uniqid(), -6)),
                    'cost_price' => $costPrice ?: 0.0000,
                    'initial_quantity' => $quantityChange,
                    'current_quantity' => $quantityChange,
                    'quantity_available' => $quantityChange,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Get live available stock for a product at a location.
     */
    public function getLocationStock(int $productId, int $locationId): float
    {
        return (float) (ProductInventory::where('product_id', $productId)
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhere('outlet_id', $locationId);
            })
            ->value('available_quantity') ?? 0);
    }

    /**
     * Reconcile batches to match the authoritative ProductInventory balance.
     */
    public function reconcileBatches(int $productId, int $locationId): void
    {
        $inventory = ProductInventory::where('product_id', $productId)
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhere('outlet_id', $locationId);
            })
            ->first();

        if (! $inventory) {
            return;
        }

        $target = (float) $inventory->available_quantity;
        $batchTotal = (float) InventoryBatch::where('product_id', $productId)
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhere('outlet_id', $locationId);
            })
            ->sum('quantity_available');

        $difference = round($target - $batchTotal, 4);

        if (abs($difference) > 0.0001) {
            $this->adjustBatches($productId, $locationId, $difference);
        }
    }
}

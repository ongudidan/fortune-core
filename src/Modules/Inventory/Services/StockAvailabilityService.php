<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;

class StockAvailabilityService
{
    /**
     * Get real-time stock availability, reserved stock, and available to promise (ATP).
     */
    public function getProductAvailability(int $productId, ?int $locationId = null): array
    {
        $query = ProductInventory::where('product_id', $productId);

        if ($locationId) {
            $query->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhere('outlet_id', $locationId);
            });
        }

        $currentStock = (float) $query->sum('current_stock');
        $availableQuantity = (float) $query->sum('available_quantity');
        $reservedStock = (float) $query->sum('reserved_stock');
        $atp = max(0, $availableQuantity - $reservedStock);

        $product = Product::find($productId);
        $reorderLevel = $product ? (float) $product->reorder_level : (float) config('fortune.inventory.low_stock_threshold', 10);
        $isLowStock = $atp <= $reorderLevel;

        return [
            'product_id' => $productId,
            'location_id' => $locationId,
            'current_stock' => $currentStock,
            'available_quantity' => $availableQuantity,
            'reserved_stock' => $reservedStock,
            'atp' => $atp,
            'reorder_level' => $reorderLevel,
            'is_low_stock' => $isLowStock,
        ];
    }

    /**
     * Build filtered and sorted query for inventory stock listing across locations.
     */
    public function buildQuery(Request $request): Builder
    {
        $locationId = $request->get('location_id') ?: $request->get('outlet_id');
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');

        $query = Product::query()
            ->with(['category', 'brand', 'unit', 'baseUnit', 'conversions.unit', 'variations.unit', 'productInventory'])
            ->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        if ($locationId) {
            $query->withSum([
                'inventories as on_hand_stock' => function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                },
            ], 'available_quantity');
        } else {
            $query->withSum('inventories as on_hand_stock', 'available_quantity');
        }

        return $query;
    }
}

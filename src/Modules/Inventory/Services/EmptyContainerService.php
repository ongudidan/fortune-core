<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\EmptyContainerMovement;
use Ongudidan\FortuneCore\Modules\Inventory\Models\OutletPartialEmpty;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductVariation;

class EmptyContainerService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected StockMovementService $stockMovementService
    ) {}

    /**
     * Calculate how many empty containers are yielded by a sale.
     */
    public function getEmptyYield(int $productId, float $quantity, ?int $unitId, int $emptyProductId): float
    {
        return $this->inventoryService->getEmptyYield($productId, $quantity, $unitId, $emptyProductId);
    }

    /**
     * Process empty container yield for a sold wholesale/retail item.
     * Atomically records partial yield in empty_container_movements, updates partial accumulator,
     * and adjusts live empty container physical inventory.
     */
    public function processSaleYield(
        int $locationId,
        int $productId,
        ProductVariation|Product $variation,
        float $quantitySold,
        ?int $customerId = null,
        string $referenceType = 'POS',
        int|string|null $referenceId = 0,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        $emptyProductId = $variation instanceof ProductVariation
            ? (int) ($variation->empty_product_id ?: 0)
            : (int) ($variation->container_product_id ?: 0);

        if ($emptyProductId <= 0) {
            return ['yield' => 0.0, 'full_empties' => 0, 'accumulated_yield' => 0.0];
        }

        $unitId = $variation->unit_id ?? null;
        $yield = $this->getEmptyYield($productId, $quantitySold, $unitId, $emptyProductId);

        if ($yield <= 0) {
            return ['yield' => 0.0, 'full_empties' => 0, 'accumulated_yield' => 0.0];
        }

        return DB::transaction(function () use (
            $locationId,
            $productId,
            $emptyProductId,
            $yield,
            $customerId,
            $referenceType,
            $referenceId,
            $userId,
            $notes
        ) {
            // 1. Lock and retrieve partial empty accumulator
            $partialEmpty = OutletPartialEmpty::where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhere('outlet_id', $locationId);
            })
                ->where('empty_product_id', $emptyProductId)
                ->lockForUpdate()
                ->first();

            if (! $partialEmpty) {
                $partialEmpty = OutletPartialEmpty::create([
                    'location_id' => $locationId,
                    'outlet_id' => $locationId,
                    'empty_product_id' => $emptyProductId,
                    'accumulated_yield' => 0.0000,
                ]);
            }

            $currentYield = (float) $partialEmpty->accumulated_yield;
            $newAccumulatedYield = $currentYield + $yield;
            $partialEmpty->update([
                'accumulated_yield' => $newAccumulatedYield,
            ]);

            // Calculate customer balance owed if customer is present
            $balanceOwed = $customerId ? $this->getCustomerContainerBalance($customerId, $emptyProductId) + $yield : 0.0;

            // 2. Log yield movement in empty_container_movements
            EmptyContainerMovement::create([
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'customer_id' => $customerId,
                'product_id' => $productId,
                'empty_product_id' => $emptyProductId,
                'quantity_yielded' => $yield,
                'quantity_returned' => 0.0000,
                'quantity_change' => $yield,
                'partial_yield_change' => $yield,
                'balance_owed' => $balanceOwed,
                'action' => 'sale_yield',
                'movement_type' => 'creation',
                'reference_type' => $referenceType,
                'reference_id' => (string) $referenceId,
                'user_id' => $userId ?: auth()->id(),
                'notes' => $notes ?: "Yield from sale of product #{$productId}",
            ]);

            // 3. Post stock intake for the empty container (physical crates/bottles arrived into the store)
            $this->stockMovementService->applyAdjustment(
                productId: $emptyProductId,
                quantityChange: $yield,
                locationId: $locationId,
                movementType: 'addition',
                referenceType: 'EMPTY_YIELD',
                referenceId: $referenceId,
                notes: "Empty container intake yield from product #{$productId}",
                userId: $userId
            );

            return [
                'yield' => $yield,
                'full_empties' => (int) floor($newAccumulatedYield),
                'accumulated_yield' => $newAccumulatedYield,
            ];
        });
    }

    /**
     * Record a customer returning physical empty containers (reduces customer debt).
     */
    public function recordCustomerReturn(
        int $customerId,
        int $emptyProductId,
        float $quantity,
        int $locationId,
        ?string $notes = null,
        ?int $userId = null
    ): EmptyContainerMovement {
        return DB::transaction(function () use (
            $customerId,
            $emptyProductId,
            $quantity,
            $locationId,
            $notes,
            $userId
        ) {
            $currentBalance = $this->getCustomerContainerBalance($customerId, $emptyProductId);
            $newBalance = max(0, $currentBalance - $quantity);

            $movement = EmptyContainerMovement::create([
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'customer_id' => $customerId,
                'empty_product_id' => $emptyProductId,
                'quantity_yielded' => 0.0000,
                'quantity_returned' => $quantity,
                'quantity_change' => $quantity,
                'balance_owed' => $newBalance,
                'action' => 'customer_return',
                'movement_type' => 'return',
                'reference_type' => 'RETURN',
                'reference_id' => 'CR-'.strtoupper(substr(uniqid(), -6)),
                'user_id' => $userId ?: auth()->id(),
                'notes' => $notes ?: 'Customer empty container return',
            ]);

            return $movement;
        });
    }

    /**
     * Get current empty container balance owed by customer.
     */
    public function getCustomerContainerBalance(int $customerId, int $emptyProductId): float
    {
        $lastMovement = EmptyContainerMovement::where('customer_id', $customerId)
            ->where('empty_product_id', $emptyProductId)
            ->latest('id')
            ->first();

        if ($lastMovement) {
            return (float) $lastMovement->balance_owed;
        }

        $totalYielded = (float) EmptyContainerMovement::where('customer_id', $customerId)
            ->where('empty_product_id', $emptyProductId)
            ->sum('quantity_yielded');

        $totalReturned = (float) EmptyContainerMovement::where('customer_id', $customerId)
            ->where('empty_product_id', $emptyProductId)
            ->sum('quantity_returned');

        return max(0.0, $totalYielded - $totalReturned);
    }
}

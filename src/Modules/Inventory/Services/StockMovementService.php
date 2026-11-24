<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryBatch;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryCostMovement;
use Ongudidan\FortuneCore\Modules\Inventory\Models\InventoryStockMovement;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;

class StockMovementService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? (class_exists(LedgerService::class) ? app(LedgerService::class) : null);
    }

    /**
     * Authoritative physical stock at a location.
     */
    public function getLocationStock(int $productId, int $locationId): float
    {
        return $this->inventoryService->getLocationStock($productId, $locationId);
    }

    /**
     * Backward-compatible alias for getLocationStock.
     */
    public function getOutletStock(int $productId, int $outletId): float
    {
        return $this->getLocationStock($productId, $outletId);
    }

    /**
     * Total stock across a branch.
     */
    public function getBranchStock(int $productId, int $branchId): float
    {
        return (float) (ProductInventory::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->sum('available_quantity') ?? 0);
    }

    /**
     * Apply stock adjustment (surplus or deficit) with automatic General Ledger posting.
     */
    public function applyAdjustment(
        int $productId,
        float $quantityChange,
        int $locationId,
        string $movementType = 'adjustment',
        string $referenceType = 'STK_ADJ',
        int|string|null $referenceId = 0,
        ?int $unitId = null,
        float $conversionFactor = 1.0,
        string $notes = '',
        ?int $userId = null,
        ?string $computerUsed = null,
        ?float $customUnitCost = null
    ): object {
        return DB::transaction(function () use (
            $productId,
            $quantityChange,
            $locationId,
            $movementType,
            $referenceType,
            $referenceId,
            $unitId,
            $conversionFactor,
            $notes,
            $userId,
            $computerUsed,
            $customUnitCost
        ) {
            $product = Product::findOrFail($productId);
            $location = Location::find($locationId);

            $baseQtyChange = (float) $quantityChange * (float) ($conversionFactor ?: 1.0);
            $unitCost = $customUnitCost !== null && (float) $customUnitCost > 0
                ? (float) $customUnitCost
                : (float) $product->cost_price;

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
                    'average_cost' => $unitCost,
                    'base_cost' => $unitCost,
                ]
            );

            $qtyBefore = (float) $inventory->available_quantity;
            $qtyAfter = max(0, $qtyBefore + $baseQtyChange);
            $valueChange = round($baseQtyChange * $unitCost, 2);

            $inventory->update([
                'current_stock' => $qtyAfter,
                'available_quantity' => $qtyAfter,
                'average_cost' => $unitCost > 0 ? $unitCost : (float) $inventory->average_cost,
            ]);

            // Record movement
            $movement = InventoryStockMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'quantity' => abs($baseQtyChange),
                'quantity_change' => $baseQtyChange,
                'running_balance' => $qtyAfter,
                'movement_type' => $baseQtyChange >= 0 ? 'addition' : 'deduction',
                'reference_type' => $referenceType,
                'reference_id' => (string) $referenceId,
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'notes' => $notes ?: "Stock adjustment ({$movementType})",
                'created_by' => $userId ?: auth()->id(),
                'user_id' => $userId ?: auth()->id(),
                'computer_used' => $computerUsed ?: request()->ip(),
                'company_id' => $location?->company_id,
                'branch_id' => $location?->branch_id,
            ]);

            // Adjust FIFO/AVCO batches
            $this->inventoryService->adjustBatches($productId, $locationId, $baseQtyChange, $unitCost);

            // Double-Entry GL Hook
            if (config('fortune.modules.ledger', true) && $this->ledgerService && abs($valueChange) > 0.001) {
                $assetAccount = config('fortune.inventory.accounts.inventory_asset', '1300');
                $shrinkageAccount = config('fortune.inventory.accounts.shrinkage_expense', '5500');
                $absVal = abs($valueChange);

                if ($baseQtyChange < 0) {
                    // Loss / Shortage / Spoilage
                    $this->ledgerService->postJournal([
                        [
                            'account_code' => $shrinkageAccount,
                            'debit' => $absVal,
                            'credit' => 0.00,
                            'entry_type' => 'InventoryShrinkage',
                            'reference' => "ADJ-{$movement->id}",
                            'description' => "Inventory shrinkage: {$product->name}",
                        ],
                        [
                            'account_code' => $assetAccount,
                            'debit' => 0.00,
                            'credit' => $absVal,
                            'entry_type' => 'InventoryShrinkage',
                            'reference' => "ADJ-{$movement->id}",
                            'description' => "Inventory asset reduction: {$product->name}",
                        ],
                    ]);
                } else {
                    // Overage / Surplus
                    $this->ledgerService->postJournal([
                        [
                            'account_code' => $assetAccount,
                            'debit' => $absVal,
                            'credit' => 0.00,
                            'entry_type' => 'InventoryOverage',
                            'reference' => "ADJ-{$movement->id}",
                            'description' => "Inventory asset addition: {$product->name}",
                        ],
                        [
                            'account_code' => $shrinkageAccount,
                            'debit' => 0.00,
                            'credit' => $absVal,
                            'entry_type' => 'InventoryOverage',
                            'reference' => "ADJ-{$movement->id}",
                            'description' => "Inventory adjustment gain: {$product->name}",
                        ],
                    ]);
                }
            }

            return (object) [
                'movement' => $movement,
                'inventory' => $inventory,
                'value_change' => $valueChange,
                'qty_after' => $qtyAfter,
            ];
        });
    }

    /**
     * Record inventory purchase (Supplier GRN) with stock intake and GL posting.
     */
    public function applyPurchase(
        int $productId,
        float $quantity,
        float $unitCost,
        int $locationId,
        ?string $reference = null,
        ?int $unitId = null,
        float $conversionFactor = 1.0,
        ?string $notes = null,
        ?int $userId = null
    ): object {
        return DB::transaction(function () use (
            $productId,
            $quantity,
            $unitCost,
            $locationId,
            $reference,
            $unitId,
            $conversionFactor,
            $notes,
            $userId
        ) {
            $product = Product::findOrFail($productId);
            $location = Location::find($locationId);

            $baseQty = $quantity * ($conversionFactor ?: 1.0);
            $totalCost = round($baseQty * $unitCost, 2);

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
                'average_cost' => $unitCost,
            ]);

            // Create batch
            $batch = InventoryBatch::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'batch_number' => 'GRN-'.strtoupper(substr(uniqid(), -6)),
                'cost_price' => $unitCost,
                'initial_quantity' => $baseQty,
                'current_quantity' => $baseQty,
                'quantity_available' => $baseQty,
                'is_active' => true,
            ]);

            // Record movement
            $movement = InventoryStockMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'batch_id' => $batch->id,
                'quantity' => $baseQty,
                'quantity_change' => $baseQty,
                'running_balance' => $newQty,
                'movement_type' => 'purchase',
                'reference_type' => 'GRN',
                'reference_id' => $reference ?: (string) $batch->id,
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'notes' => $notes ?: "Goods Receipt Note for {$product->name}",
                'created_by' => $userId ?: auth()->id(),
                'user_id' => $userId ?: auth()->id(),
                'company_id' => $location?->company_id,
                'branch_id' => $location?->branch_id,
            ]);

            // Record cost movement
            InventoryCostMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'batch_id' => $batch->id,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'cost_type' => config('fortune.inventory.valuation_method', 'fifo'),
                'reference_type' => 'GRN',
                'reference_id' => $reference ?: (string) $batch->id,
                'company_id' => $location?->company_id,
                'branch_id' => $location?->branch_id,
            ]);

            // GL Posting: Debit Inventory Asset 1300, Credit GRNI Clearing 2050
            if (config('fortune.modules.ledger', true) && $this->ledgerService && $totalCost > 0) {
                $assetAccount = config('fortune.inventory.accounts.inventory_asset', '1300');
                $clearingAccount = config('fortune.inventory.accounts.grni_clearing', '2050');

                $this->ledgerService->postJournal([
                    [
                        'account_code' => $assetAccount,
                        'debit' => $totalCost,
                        'credit' => 0.00,
                        'entry_type' => 'GoodsReceipt',
                        'reference' => $reference ?: "GRN-{$movement->id}",
                        'description' => "Inventory asset intake for {$product->name}",
                    ],
                    [
                        'account_code' => $clearingAccount,
                        'debit' => 0.00,
                        'credit' => $totalCost,
                        'entry_type' => 'GoodsReceipt',
                        'reference' => $reference ?: "GRN-{$movement->id}",
                        'description' => "GRNI clearing / AP liability for {$product->name}",
                    ],
                ]);
            }

            return (object) [
                'movement' => $movement,
                'batch' => $batch,
                'inventory' => $inventory,
                'total_cost' => $totalCost,
            ];
        });
    }

    /**
     * Record stock sale deduction and COGS.
     */
    public function applySale(
        int $productId,
        float $quantity,
        int $locationId,
        ?string $reference = null,
        ?int $unitId = null,
        float $conversionFactor = 1.0,
        ?string $notes = null,
        ?int $userId = null
    ): object {
        return DB::transaction(function () use (
            $productId,
            $quantity,
            $locationId,
            $reference,
            $unitId,
            $conversionFactor,
            $notes,
            $userId
        ) {
            $product = Product::findOrFail($productId);
            $location = Location::find($locationId);

            $baseQty = $quantity * ($conversionFactor ?: 1.0);
            $inventory = ProductInventory::where('product_id', $productId)
                ->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('outlet_id', $locationId);
                })
                ->lockForUpdate()
                ->first();

            $unitCost = $inventory ? (float) $inventory->average_cost : (float) $product->cost_price;
            $newQty = max(0, ($inventory ? (float) $inventory->available_quantity : 0) - $baseQty);

            if ($inventory) {
                $inventory->update([
                    'current_stock' => $newQty,
                    'available_quantity' => $newQty,
                ]);
            }

            // Consume FIFO batches
            $this->inventoryService->adjustBatches($productId, $locationId, -$baseQty);

            $movement = InventoryStockMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'outlet_id' => $locationId,
                'quantity' => $baseQty,
                'quantity_change' => -$baseQty,
                'running_balance' => $newQty,
                'movement_type' => 'sale',
                'reference_type' => 'SALE',
                'reference_id' => $reference ?: 'POS',
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'notes' => $notes ?: "Sale deduction for {$product->name}",
                'created_by' => $userId ?: auth()->id(),
                'user_id' => $userId ?: auth()->id(),
                'company_id' => $location?->company_id,
                'branch_id' => $location?->branch_id,
            ]);

            // GL Posting: Debit COGS 5000, Credit Inventory Asset 1300
            $cogsValue = round($baseQty * $unitCost, 2);
            if (config('fortune.modules.ledger', true) && $this->ledgerService && $cogsValue > 0) {
                $cogsAccount = config('fortune.inventory.accounts.cogs', '5000');
                $assetAccount = config('fortune.inventory.accounts.inventory_asset', '1300');

                $this->ledgerService->postJournal([
                    [
                        'account_code' => $cogsAccount,
                        'debit' => $cogsValue,
                        'credit' => 0.00,
                        'entry_type' => 'CostOfGoodsSold',
                        'reference' => $reference ?: "SALE-{$movement->id}",
                        'description' => "COGS for {$product->name}",
                    ],
                    [
                        'account_code' => $assetAccount,
                        'debit' => 0.00,
                        'credit' => $cogsValue,
                        'entry_type' => 'CostOfGoodsSold',
                        'reference' => $reference ?: "SALE-{$movement->id}",
                        'description' => "Inventory asset relief for {$product->name}",
                    ],
                ]);
            }

            return (object) [
                'movement' => $movement,
                'inventory' => $inventory,
                'cogs_value' => $cogsValue,
            ];
        });
    }
}

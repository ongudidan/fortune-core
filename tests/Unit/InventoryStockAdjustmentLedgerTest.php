<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\StockTaking;
use Ongudidan\FortuneCore\Modules\Inventory\Models\StockTakingItem;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\RetailStockTakingService;
use Ongudidan\FortuneCore\Modules\Inventory\Services\StockMovementService;
use Ongudidan\FortuneCore\Modules\Payments\Models\Account;
use Ongudidan\FortuneCore\Modules\Wallets\Models\EntityAccountLedger;
use Ongudidan\FortuneCore\Tests\TestCase;

class InventoryStockAdjustmentLedgerTest extends TestCase
{
    public function test_stock_adjustment_writes_balanced_gl_entries(): void
    {
        $piece = Unit::create(['name' => 'Bag', 'short_name' => 'bg']);

        $location = Location::create([
            'name' => 'Warehouse A',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Cement 50kg',
            'sku' => 'CMT-50',
            'unit_id' => $piece->id,
            'cost_price' => 750.00,
            'selling_price' => 850.00,
            'is_active' => true,
        ]);

        $movementService = app(StockMovementService::class);

        // 1. Initial intake of 100 bags (Purchase GRN)
        $movementService->applyPurchase(
            productId: $product->id,
            quantity: 100,
            unitCost: 750.00,
            locationId: $location->id,
            reference: 'GRN-001'
        );

        $assetAccount = Account::where('code', '1300')->first();
        $this->assertNotNull($assetAccount);
        $this->assertEquals(75000.00, (float) $assetAccount->balance);

        // 2. Physical count reports a damage/shrinkage loss of 4 bags (4 * 750 = 3,000)
        $movementService->applyAdjustment(
            productId: $product->id,
            quantityChange: -4,
            locationId: $location->id,
            movementType: 'deduction',
            notes: '4 bags damaged by water leak'
        );

        $assetAccount->refresh();
        $shrinkageAccount = Account::where('code', '5500')->first();

        // Asset account 1300 decreased from 75,000 to 72,000
        $this->assertEquals(72000.00, (float) $assetAccount->balance);
        // Shrinkage expense 5500 increased by 3,000
        $this->assertEquals(3000.00, (float) $shrinkageAccount->balance);

        // Verify ledger entry integrity
        $lastLedger = EntityAccountLedger::where('account_code', '5500')->latest('id')->first();
        $this->assertNotNull($lastLedger);
        $this->assertEquals(3000.00, (float) $lastLedger->debit);
    }

    public function test_stock_taking_finalization_computes_variance_and_posts_gl(): void
    {
        $piece = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        $location = Location::create([
            'name' => 'Depot 1',
            'type' => 'retail',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Rice 25kg',
            'sku' => 'RICE-25',
            'unit_id' => $piece->id,
            'cost_price' => 2000.00,
            'selling_price' => 2400.00,
            'is_active' => true,
        ]);

        $movementService = app(StockMovementService::class);

        // Set initial stock to 50 bags
        $movementService->applyPurchase(
            productId: $product->id,
            quantity: 50,
            unitCost: 2000.00,
            locationId: $location->id
        );

        // Create StockTaking with physical count of 47 bags (3 bags shortage = KES 6,000 loss)
        $stockTaking = StockTaking::create([
            'location_id' => $location->id,
            'date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        StockTakingItem::create([
            'stock_taking_id' => $stockTaking->id,
            'product_id' => $product->id,
            'book_quantity' => 50,
            'physical_quantity' => 47,
            'cost_price' => 2000.00,
        ]);

        $stockTakingService = app(RetailStockTakingService::class);
        $finalized = $stockTakingService->finalizeStockTaking($stockTaking->id);

        $this->assertEquals('completed', $finalized->status);
        $this->assertEquals(-6000.00, (float) $finalized->total_variance_value);

        // Physical stock on location is now 47
        $currentStock = $movementService->getLocationStock($product->id, $location->id);
        $this->assertEquals(47.0, $currentStock);
    }
}

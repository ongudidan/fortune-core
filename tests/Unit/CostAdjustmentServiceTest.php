<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\CostAdjustmentService;
use Ongudidan\FortuneCore\Modules\Inventory\Services\StockMovementService;
use Ongudidan\FortuneCore\Modules\Wallets\Models\EntityAccountLedger;
use Ongudidan\FortuneCore\Tests\TestCase;

class CostAdjustmentServiceTest extends TestCase
{
    public function test_inventory_cost_revaluation_updates_average_cost_batches_and_posts_gl(): void
    {
        $unit = Unit::create(['name' => 'Box', 'short_name' => 'bx']);

        $location = Location::create([
            'name' => 'Main Warehouse',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Premium Coffee 500g',
            'sku' => 'COFFEE-500',
            'unit_id' => $unit->id,
            'cost_price' => 500.00,
            'selling_price' => 650.00,
            'is_active' => true,
        ]);

        $movementService = app(StockMovementService::class);
        $costAdjService = app(CostAdjustmentService::class);

        // 1. Initial intake: 100 boxes @ 500 = KES 50,000
        $movementService->applyPurchase(
            productId: $product->id,
            quantity: 100,
            unitCost: 500.00,
            locationId: $location->id,
            reference: 'GRN-COFFEE-01'
        );

        // 2. Upward Cost Revaluation: supplier revised price upwards to KES 550 (+50 diff per unit)
        // Total valuation impact = +50 * 100 boxes = +KES 5,000 Gain
        $gainAdjustment = $costAdjService->applyCostAdjustment(
            productId: $product->id,
            newCost: 550.00,
            locationId: $location->id,
            reason: 'Supplier invoice price increase reconciliation'
        );

        $this->assertNotNull($gainAdjustment);
        $this->assertEquals(500.00, (float) $gainAdjustment->old_unit_cost);
        $this->assertEquals(550.00, (float) $gainAdjustment->new_unit_cost);
        $this->assertEquals(5000.00, (float) $gainAdjustment->total_value_impact);

        // Verify inventory average cost updated
        $inventory = ProductInventory::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertEquals(550.00, (float) $inventory->average_cost);

        // Verify GL: Debit 1300 (Asset) 5000, Credit 5500 (Gain) 5000
        $gainLedger = EntityAccountLedger::where('account_code', '1300')
            ->where('entry_type', 'CostAdjustmentGain')
            ->latest('id')
            ->first();
        $this->assertNotNull($gainLedger);
        $this->assertEquals(5000.00, (float) $gainLedger->debit);

        // 3. Downward Cost Revaluation: market drop to KES 520 (-30 diff per unit)
        // Total valuation impact = -30 * 100 boxes = -KES 3,000 Write-down
        $lossAdjustment = $costAdjService->applyCostAdjustment(
            productId: $product->id,
            newCost: 520.00,
            locationId: $location->id,
            reason: 'Market price reduction write-down'
        );

        $this->assertNotNull($lossAdjustment);
        $this->assertEquals(550.00, (float) $lossAdjustment->old_unit_cost);
        $this->assertEquals(520.00, (float) $lossAdjustment->new_unit_cost);
        $this->assertEquals(-3000.00, (float) $lossAdjustment->total_value_impact);

        // Verify GL: Debit 5500 (Loss) 3000, Credit 1300 (Asset) 3000
        $lossLedger = EntityAccountLedger::where('account_code', '5500')
            ->where('entry_type', 'CostAdjustmentLoss')
            ->latest('id')
            ->first();
        $this->assertNotNull($lossLedger);
        $this->assertEquals(3000.00, (float) $lossLedger->debit);
    }
}

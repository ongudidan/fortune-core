<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\SaleService;
use Ongudidan\FortuneCore\Modules\Inventory\Services\StockMovementService;
use Ongudidan\FortuneCore\Modules\Payments\Models\Account;
use Ongudidan\FortuneCore\Modules\Wallets\Models\EntityAccountLedger;
use Ongudidan\FortuneCore\Tests\TestCase;

class SaleServiceTest extends TestCase
{
    public function test_sale_creation_deducts_stock_and_posts_balanced_gl_entries(): void
    {
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        $location = Location::create([
            'name' => 'Retail Outlet A',
            'type' => 'retail',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Cooking Oil 1L',
            'sku' => 'OIL-1L',
            'unit_id' => $unit->id,
            'cost_price' => 200.00,
            'selling_price' => 250.00,
            'is_active' => true,
        ]);

        $movementService = app(StockMovementService::class);
        $saleService = app(SaleService::class);

        // 1. Initial intake: 50 units @ 200 = KES 10,000
        $movementService->applyPurchase(
            productId: $product->id,
            quantity: 50,
            unitCost: 200.00,
            locationId: $location->id,
            reference: 'GRN-SETUP'
        );

        $initialStock = $movementService->getLocationStock($product->id, $location->id);
        $this->assertEquals(50.0, $initialStock);

        // 2. Perform a split sale: 5 pieces @ 250 = KES 1,250
        // Paid: KES 750 Cash, KES 500 M-Pesa
        $sale = $saleService->createSale([
            'location_id' => $location->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 250.00,
                    'unit_cost' => 200.00,
                ],
            ],
            'cash_paid' => 750.00,
            'mpesa_paid' => 500.00,
            'payment_method' => 'split',
        ]);

        $this->assertNotNull($sale);
        $this->assertEquals(1250.00, (float) $sale->total_amount);
        $this->assertEquals('paid', $sale->payment_status);

        // Stock deduction check: 50 - 5 = 45
        $stockAfter = $movementService->getLocationStock($product->id, $location->id);
        $this->assertEquals(45.0, $stockAfter);

        // General Ledger Checks
        // Cash Account (1001) should have increased by 750
        $cashAccount = Account::where('code', '1001')->first();
        $this->assertNotNull($cashAccount);
        $this->assertEquals(750.00, (float) $cashAccount->balance);

        // M-Pesa Account (1020) should have increased by 500
        $mpesaAccount = Account::where('code', '1020')->first();
        $this->assertNotNull($mpesaAccount);
        $this->assertEquals(500.00, (float) $mpesaAccount->balance);

        // Sales Revenue (4000) credit entry of 1250
        $revenueLedger = EntityAccountLedger::where('account_code', '4000')
            ->where('entry_type', 'SaleRevenue')
            ->latest('id')
            ->first();
        $this->assertNotNull($revenueLedger);
        $this->assertEquals(1250.00, (float) $revenueLedger->credit);

        // COGS (5000) debit entry of 1,000 (5 * 200)
        $cogsLedger = EntityAccountLedger::where('account_code', '5000')
            ->where('entry_type', 'CostOfGoodsSold')
            ->latest('id')
            ->first();
        $this->assertNotNull($cogsLedger);
        $this->assertEquals(1000.00, (float) $cogsLedger->debit);

        // 3. Process Customer Return: 1 piece returned
        $saleReturn = $saleService->processReturn(
            sale: $sale,
            items: [
                [
                    'sale_item_id' => $sale->items->first()->id,
                    'quantity_returned' => 1,
                ],
            ],
            reason: 'Wrong size bought'
        );

        $this->assertNotNull($saleReturn);
        $this->assertEquals(250.00, (float) $saleReturn->total_refund_amount);

        // Stock after return: 45 + 1 = 46
        $stockAfterReturn = $movementService->getLocationStock($product->id, $location->id);
        $this->assertEquals(46.0, $stockAfterReturn);
    }
}

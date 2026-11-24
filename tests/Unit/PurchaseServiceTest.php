<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\PurchaseService;
use Ongudidan\FortuneCore\Modules\Inventory\Services\StockMovementService;
use Ongudidan\FortuneCore\Modules\Payments\Models\Account;
use Ongudidan\FortuneCore\Modules\Wallets\Models\EntityAccountLedger;
use Ongudidan\FortuneCore\Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    public function test_purchase_order_grn_intake_and_settlement_lifecycle(): void
    {
        $unit = Unit::create(['name' => 'Bale', 'short_name' => 'ble']);

        $location = Location::create([
            'name' => 'Central Depot',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Wheat Flour 24x2kg',
            'sku' => 'FLOUR-24',
            'unit_id' => $unit->id,
            'cost_price' => 1800.00,
            'selling_price' => 2100.00,
            'is_active' => true,
        ]);

        $purchaseService = app(PurchaseService::class);
        $movementService = app(StockMovementService::class);

        // 1. Create Purchase Order for 20 bales @ 1,800 = KES 36,000
        $po = $purchaseService->createPurchaseOrder([
            'supplier_id' => 1,
            'location_id' => $location->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => 20,
                    'unit_cost' => 1800.00,
                ],
            ],
            'notes' => 'Weekly flour delivery',
        ]);

        $this->assertNotNull($po);
        $this->assertEquals('pending', $po->status);
        $this->assertEquals(36000.00, (float) $po->total_amount);

        // 2. Receive Goods via GRN
        $purchase = $purchaseService->receiveGoods($po);

        $this->assertNotNull($purchase);
        $this->assertEquals('received', $purchase->status);
        $this->assertEquals(36000.00, (float) $purchase->total_amount);

        // Check stock availability: exactly 20 bales
        $stock = $movementService->getLocationStock($product->id, $location->id);
        $this->assertEquals(20.0, $stock);

        // Check PO status transitioned to 'received'
        $po->refresh();
        $this->assertEquals('received', $po->status);

        // Double-entry GL checks:
        // Asset 1300 balance should be 36,000
        $assetAccount = Account::where('code', '1300')->first();
        $this->assertNotNull($assetAccount);
        $this->assertEquals(36000.00, (float) $assetAccount->balance);

        // GRNI Clearing 2050 credit entry
        $grniLedger = EntityAccountLedger::where('account_code', '2050')
            ->where('entry_type', 'GoodsReceipt')
            ->latest('id')
            ->first();
        $this->assertNotNull($grniLedger);
        $this->assertEquals(36000.00, (float) $grniLedger->credit);

        // 3. Settle Supplier Bill via Bank disbursement
        $purchaseService->settleSupplierInvoice($purchase, 36000.00, paymentMethod: 'bank');

        $purchase->refresh();
        $this->assertEquals('paid', $purchase->status);
        $this->assertEquals(36000.00, (float) $purchase->amount_paid);

        // GL check: Debit 2050 (GRNI), Credit 1010 (Bank)
        $settleLedger = EntityAccountLedger::where('account_code', '2050')
            ->where('entry_type', 'SupplierPayment')
            ->latest('id')
            ->first();
        $this->assertNotNull($settleLedger);
        $this->assertEquals(36000.00, (float) $settleLedger->debit);
    }
}

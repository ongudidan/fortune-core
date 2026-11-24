<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\OutletPartialEmpty;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductVariation;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\EmptyContainerService;
use Ongudidan\FortuneCore\Tests\TestCase;

class EmptyContainerYieldTest extends TestCase
{
    public function test_sale_yield_accumulates_fractional_empty_containers(): void
    {
        $piece = Unit::create(['name' => 'Bottle', 'short_name' => 'btl']);
        $crateUnit = Unit::create(['name' => 'Crate (24)', 'short_name' => 'crt']);

        $location = Location::create([
            'name' => 'Retail Outlet 1',
            'type' => 'retail',
            'is_active' => true,
        ]);

        // Empty Bottle container product
        $emptyBottle = Product::create([
            'name' => 'Empty Glass Bottle 500ml',
            'sku' => 'EMP-BTL-500',
            'unit_id' => $piece->id,
            'is_returnable_container' => true,
            'is_active' => true,
        ]);

        // Beverage product
        $soda = Product::create([
            'name' => 'Soda 500ml (Glass)',
            'sku' => 'SODA-500',
            'unit_id' => $piece->id,
            'base_unit_id' => $piece->id,
            'cost_price' => 50.00,
            'selling_price' => 70.00,
            'container_product_id' => $emptyBottle->id,
            'is_active' => true,
        ]);

        $variation = ProductVariation::create([
            'product_id' => $soda->id,
            'name' => 'Soda 500ml Single Bottle',
            'unit_id' => $piece->id,
            'conversion_factor' => 1.0000,
            'empty_product_id' => $emptyBottle->id,
            'is_active' => true,
        ]);

        $service = app(EmptyContainerService::class);

        // 1. Sell 10 bottles
        $res1 = $service->processSaleYield(
            locationId: $location->id,
            productId: $soda->id,
            variation: $variation,
            quantitySold: 10,
            customerId: null
        );

        $this->assertEquals(10.0, $res1['yield']);
        $this->assertEquals(10.0, $res1['accumulated_yield']);
        $this->assertEquals(10, $res1['full_empties']);

        // Check accumulator record in DB
        $partial = OutletPartialEmpty::where('location_id', $location->id)
            ->where('empty_product_id', $emptyBottle->id)
            ->first();
        $this->assertNotNull($partial);
        $this->assertEquals(10.0, (float) $partial->accumulated_yield);

        // 2. Sell another 14 bottles -> accumulated becomes 24
        $res2 = $service->processSaleYield(
            locationId: $location->id,
            productId: $soda->id,
            variation: $variation,
            quantitySold: 14,
            customerId: null
        );

        $this->assertEquals(14.0, $res2['yield']);
        $this->assertEquals(24.0, $res2['accumulated_yield']);
        $this->assertEquals(24, $res2['full_empties']);
    }

    public function test_customer_return_reduces_container_debt(): void
    {
        $piece = Unit::create(['name' => 'Bottle', 'short_name' => 'btl']);

        $location = Location::create([
            'name' => 'Main Outlet',
            'type' => 'retail',
            'is_active' => true,
        ]);

        $emptyBottle = Product::create([
            'name' => 'Empty Bottle',
            'sku' => 'EMP-001',
            'unit_id' => $piece->id,
            'is_returnable_container' => true,
            'is_active' => true,
        ]);

        $soda = Product::create([
            'name' => 'Soda',
            'sku' => 'SODA-01',
            'unit_id' => $piece->id,
            'container_product_id' => $emptyBottle->id,
            'is_active' => true,
        ]);

        $variation = ProductVariation::create([
            'product_id' => $soda->id,
            'name' => 'Single Bottle',
            'unit_id' => $piece->id,
            'empty_product_id' => $emptyBottle->id,
            'is_active' => true,
        ]);

        $service = app(EmptyContainerService::class);

        // Customer ID 5 buys 20 bottles
        $service->processSaleYield(
            locationId: $location->id,
            productId: $soda->id,
            variation: $variation,
            quantitySold: 20,
            customerId: 5
        );

        $owedBefore = $service->getCustomerContainerBalance(5, $emptyBottle->id);
        $this->assertEquals(20.0, $owedBefore);

        // Customer returns 15 empty bottles
        $service->recordCustomerReturn(
            customerId: 5,
            emptyProductId: $emptyBottle->id,
            quantity: 15,
            locationId: $location->id
        );

        $owedAfter = $service->getCustomerContainerBalance(5, $emptyBottle->id);
        $this->assertEquals(5.0, $owedAfter);
    }
}

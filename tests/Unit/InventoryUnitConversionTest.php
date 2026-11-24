<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Inventory\Models\Location;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductUnitConversion;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Unit;
use Ongudidan\FortuneCore\Modules\Inventory\Services\InventoryService;
use Ongudidan\FortuneCore\Tests\TestCase;

class InventoryUnitConversionTest extends TestCase
{
    public function test_carton_to_piece_conversion_calculates_correct_base_quantity(): void
    {
        $piece = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
        $carton = Unit::create(['name' => 'Carton (24)', 'short_name' => 'ctn']);

        $product = Product::create([
            'name' => 'Cooking Oil 1L',
            'sku' => 'OIL-1L',
            'unit_id' => $piece->id,
            'base_unit_id' => $piece->id,
            'cost_price' => 250.00,
            'selling_price' => 300.00,
            'wholesale_price' => 280.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'from_unit_id' => $carton->id,
            'to_unit_id' => $piece->id,
            'unit_id' => $carton->id,
            'operator' => '*',
            'factor' => 24.0000,
            'wholesale_price' => 6720.00,
        ]);

        $service = app(InventoryService::class);

        // 3 cartons = 3 * 24 = 72 pieces
        $baseQty = $service->convertToBase($product->id, 3, $carton->id);
        $this->assertEquals(72.0, $baseQty);

        // Fractional: 2.5 cartons = 2.5 * 24 = 60 pieces
        $fractionalQty = $service->convertToBase($product->id, 2.5, $carton->id);
        $this->assertEquals(60.0, $fractionalQty);
    }

    public function test_increasing_and_decreasing_stock_with_packaging_units(): void
    {
        $piece = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
        $carton = Unit::create(['name' => 'Carton (12)', 'short_name' => 'ctn']);

        $location = Location::create([
            'name' => 'Central Warehouse',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Detergent 500g',
            'sku' => 'DET-500G',
            'unit_id' => $piece->id,
            'base_unit_id' => $piece->id,
            'cost_price' => 100.00,
            'selling_price' => 120.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'unit_id' => $carton->id,
            'operator' => '*',
            'factor' => 12.0000,
        ]);

        $service = app(InventoryService::class);

        // Increase by 5 cartons (60 pieces)
        $inv = $service->increaseStock($product->id, 5, $location->id, $carton->id);
        $this->assertEquals(60.0, (float) $inv->available_quantity);

        // Decrease by 1 carton (12 pieces)
        $inv = $service->decreaseStock($product->id, 1, $location->id, $carton->id);
        $this->assertEquals(48.0, (float) $inv->available_quantity);

        // Decrease by 6 loose pieces
        $inv = $service->decreaseStock($product->id, 6, $location->id, $piece->id);
        $this->assertEquals(42.0, (float) $inv->available_quantity);
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Ongudidan\FortuneCore\Models\BaseModel;

class Product extends BaseModel
{
    protected $baseTable = 'products';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'category_id',
        'brand_id',
        'unit_id',
        'base_unit_id',
        'reorder_unit_id',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'reorder_level',
        'has_variations',
        'is_returnable_container',
        'container_product_id',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'has_variations' => 'boolean',
        'is_returnable_container' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function reorderUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'reorder_unit_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class, 'product_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(ProductInventory::class, 'product_id');
    }

    public function productInventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class, 'product_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'product_id');
    }

    public function containerProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'container_product_id');
    }
}

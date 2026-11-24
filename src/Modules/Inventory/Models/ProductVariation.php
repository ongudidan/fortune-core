<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class ProductVariation extends BaseModel
{
    protected $baseTable = 'product_variations';

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'unit_id',
        'conversion_factor',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'empty_product_id',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function emptyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'empty_product_id');
    }
}

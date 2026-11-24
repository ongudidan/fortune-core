<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SaleItem extends BaseModel
{
    protected $baseTable = 'sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variation_id',
        'unit_id',
        'quantity',
        'conversion_factor',
        'unit_price',
        'unit_cost',
        'total_price',
        'tax_amount',
        'discount_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}

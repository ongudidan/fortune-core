<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class PurchaseOrderItem extends BaseModel
{
    protected $baseTable = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variation_id',
        'unit_id',
        'conversion_factor',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'quantity_ordered' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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

<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SaleReturnItem extends BaseModel
{
    protected $baseTable = 'sale_return_items';

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity_returned',
        'unit_price',
        'refund_amount',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

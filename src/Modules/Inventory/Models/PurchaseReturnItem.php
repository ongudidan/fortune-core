<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class PurchaseReturnItem extends BaseModel
{
    protected $baseTable = 'purchase_return_items';

    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'quantity_returned',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

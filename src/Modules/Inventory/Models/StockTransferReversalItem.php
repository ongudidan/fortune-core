<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTransferReversalItem extends BaseModel
{
    protected $baseTable = 'stock_transfer_reversal_items';

    protected $fillable = [
        'stock_transfer_reversal_id',
        'product_id',
        'batch_id',
        'quantity_reversed',
    ];

    protected $casts = [
        'quantity_reversed' => 'decimal:4',
    ];

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(StockTransferReversal::class, 'stock_transfer_reversal_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

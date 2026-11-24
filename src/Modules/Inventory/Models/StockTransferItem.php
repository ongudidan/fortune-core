<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTransferItem extends BaseModel
{
    protected $baseTable = 'stock_transfer_items';

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'batch_id',
        'unit_id',
        'quantity_sent',
        'quantity_received',
        'variance',
    ];

    protected $casts = [
        'quantity_sent' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'variance' => 'decimal:4',
    ];

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}

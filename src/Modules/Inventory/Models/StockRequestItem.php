<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockRequestItem extends BaseModel
{
    protected $baseTable = 'stock_request_items';

    protected $fillable = [
        'stock_request_id',
        'product_id',
        'unit_id',
        'quantity_requested',
        'quantity_approved',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:4',
        'quantity_approved' => 'decimal:4',
    ];

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}

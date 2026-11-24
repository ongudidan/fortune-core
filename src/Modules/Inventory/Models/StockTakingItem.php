<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTakingItem extends BaseModel
{
    protected $baseTable = 'stock_taking_items';

    protected $fillable = [
        'stock_taking_id',
        'product_id',
        'book_quantity',
        'physical_quantity',
        'variance_quantity',
        'cost_price',
        'variance_value',
    ];

    protected $casts = [
        'book_quantity' => 'decimal:4',
        'physical_quantity' => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'variance_value' => 'decimal:2',
    ];

    public function stockTaking(): BelongsTo
    {
        return $this->belongsTo(StockTaking::class, 'stock_taking_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(StockTakingItemBreakdown::class, 'stock_taking_item_id');
    }
}

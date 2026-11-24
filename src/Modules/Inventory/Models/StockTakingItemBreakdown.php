<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTakingItemBreakdown extends BaseModel
{
    protected $baseTable = 'stock_taking_item_breakdowns';

    protected $fillable = [
        'stock_taking_item_id',
        'unit_id',
        'packages_counted',
        'loose_units_counted',
        'normalized_quantity',
    ];

    protected $casts = [
        'packages_counted' => 'decimal:4',
        'loose_units_counted' => 'decimal:4',
        'normalized_quantity' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockTakingItem::class, 'stock_taking_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}

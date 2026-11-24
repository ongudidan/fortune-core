<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryStockAdjustment extends BaseModel
{
    protected $baseTable = 'inventory_stock_adjustments';

    protected $fillable = [
        'stock_taking_id',
        'location_id',
        'outlet_id',
        'product_id',
        'quantity',
        'type', // addition, deduction
        'unit_cost',
        'total_value',
        'reason',
        'approved_by',
        'company_id',
        'branch_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:2',
    ];

    public function stockTaking(): BelongsTo
    {
        return $this->belongsTo(StockTaking::class, 'stock_taking_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }
}

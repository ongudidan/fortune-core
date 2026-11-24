<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryCostAdjustment extends BaseModel
{
    protected $baseTable = 'inventory_cost_adjustments';

    protected $fillable = [
        'location_id',
        'product_id',
        'unit_id',
        'factor',
        'old_unit_cost',
        'new_unit_cost',
        'current_stock',
        'cost_difference',
        'total_value_impact',
        'reason',
        'status',
        'journal_entry_id',
        'user_id',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'factor' => 'decimal:4',
        'old_unit_cost' => 'decimal:4',
        'new_unit_cost' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'cost_difference' => 'decimal:4',
        'total_value_impact' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }
}

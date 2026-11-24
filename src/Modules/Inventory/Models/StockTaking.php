<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTaking extends BaseModel
{
    protected $baseTable = 'stock_takings';

    protected $fillable = [
        'location_id',
        'outlet_id',
        'branch_id',
        'date',
        'status', // draft, in_progress, completed, approved
        'performed_by',
        'approved_by',
        'total_variance_value',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'total_variance_value' => 'decimal:2',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'performed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTakingItem::class, 'stock_taking_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryStockAdjustment::class, 'stock_taking_id');
    }
}

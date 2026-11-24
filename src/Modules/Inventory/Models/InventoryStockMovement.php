<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryStockMovement extends BaseModel
{
    protected $baseTable = 'inventory_stock_movements';

    protected $fillable = [
        'product_id',
        'location_id',
        'outlet_id',
        'batch_id',
        'quantity',
        'quantity_change',
        'running_balance',
        'movement_type', // purchase, sale, transfer_in, transfer_out, adjustment, return, write_off
        'reference_type',
        'reference_id',
        'unit_id',
        'conversion_factor',
        'notes',
        'created_by',
        'user_id',
        'computer_used',
        'company_id',
        'branch_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_change' => 'decimal:4',
        'running_balance' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }
}

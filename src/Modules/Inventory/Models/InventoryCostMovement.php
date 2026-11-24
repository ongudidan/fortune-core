<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryCostMovement extends BaseModel
{
    protected $baseTable = 'inventory_cost_movements';

    protected $fillable = [
        'product_id',
        'location_id',
        'outlet_id',
        'batch_id',
        'unit_cost',
        'total_cost',
        'cost_type', // fifo, avco
        'reference_type',
        'reference_id',
        'company_id',
        'branch_id',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}

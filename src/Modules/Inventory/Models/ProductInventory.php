<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class ProductInventory extends BaseModel
{
    protected $baseTable = 'product_inventories';

    public static int $disableSelfHealDepth = 0;

    protected $fillable = [
        'product_id',
        'location_id',
        'outlet_id',
        'company_id',
        'branch_id',
        'current_stock',
        'available_quantity',
        'reserved_stock',
        'reorder_level',
        'average_cost',
        'base_cost',
    ];

    protected $casts = [
        'current_stock' => 'decimal:4',
        'available_quantity' => 'decimal:4',
        'reserved_stock' => 'decimal:4',
        'reorder_level' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'base_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'outlet_id');
    }
}

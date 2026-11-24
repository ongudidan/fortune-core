<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryBatch extends BaseModel
{
    protected $baseTable = 'inventory_batches';

    protected $fillable = [
        'product_id',
        'location_id',
        'outlet_id',
        'batch_number',
        'cost_price',
        'selling_price',
        'mfg_date',
        'expiry_date',
        'initial_quantity',
        'current_quantity',
        'quantity_available',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'mfg_date' => 'date',
        'expiry_date' => 'date',
        'initial_quantity' => 'decimal:4',
        'current_quantity' => 'decimal:4',
        'quantity_available' => 'decimal:4',
        'is_active' => 'boolean',
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

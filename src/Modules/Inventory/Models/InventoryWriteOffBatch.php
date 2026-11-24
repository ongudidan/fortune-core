<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryWriteOffBatch extends BaseModel
{
    protected $baseTable = 'inventory_write_off_batches';

    protected $fillable = [
        'inventory_write_off_id',
        'product_id',
        'batch_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(InventoryWriteOff::class, 'inventory_write_off_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}

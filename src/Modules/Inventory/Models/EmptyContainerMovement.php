<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class EmptyContainerMovement extends BaseModel
{
    protected $baseTable = 'empty_container_movements';

    protected $fillable = [
        'location_id',
        'outlet_id',
        'customer_id',
        'product_id',
        'empty_product_id',
        'quantity_yielded',
        'quantity_returned',
        'quantity_change',
        'partial_yield_change',
        'balance_owed',
        'action', // sale_yield, customer_return, supplier_dispatch
        'movement_type', // creation, return, deduction
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity_yielded' => 'decimal:4',
        'quantity_returned' => 'decimal:4',
        'quantity_change' => 'decimal:4',
        'partial_yield_change' => 'decimal:4',
        'balance_owed' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function emptyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'empty_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }
}

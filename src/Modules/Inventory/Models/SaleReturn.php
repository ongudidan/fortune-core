<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class SaleReturn extends BaseModel
{
    protected $baseTable = 'sale_returns';

    protected $fillable = [
        'return_number',
        'sale_id',
        'location_id',
        'customer_id',
        'total_refund_amount',
        'status',
        'reason',
        'processed_by',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_return_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'processed_by');
    }
}

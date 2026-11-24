<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTransfer extends BaseModel
{
    protected $baseTable = 'stock_transfers';

    protected $fillable = [
        'transfer_no',
        'from_location_id',
        'to_location_id',
        'from_outlet_id',
        'to_outlet_id',
        'status', // in_transit, completed, cancelled
        'dispatched_by',
        'received_by',
        'dispatched_at',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }
}

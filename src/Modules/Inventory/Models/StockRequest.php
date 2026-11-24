<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockRequest extends BaseModel
{
    protected $baseTable = 'stock_requests';

    protected $fillable = [
        'request_no',
        'from_location_id',
        'to_location_id',
        'status', // pending, approved, dispatched, received, rejected
        'requested_by',
        'approved_by',
        'notes',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class, 'stock_request_id');
    }
}

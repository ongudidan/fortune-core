<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class InventoryWriteOff extends BaseModel
{
    protected $baseTable = 'inventory_write_offs';

    protected $fillable = [
        'location_id',
        'outlet_id',
        'reference_no',
        'total_loss_value',
        'status', // draft, approved, completed
        'reason', // spoilage, breakage, expiry
        'requested_by',
        'approved_by',
    ];

    protected $casts = [
        'total_loss_value' => 'decimal:2',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryWriteOffBatch::class, 'inventory_write_off_id');
    }
}

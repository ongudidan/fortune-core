<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class StockTransferReversal extends BaseModel
{
    protected $baseTable = 'stock_transfer_reversals';

    protected $fillable = [
        'stock_transfer_id',
        'reversal_no',
        'status',
        'reason',
        'requested_by',
        'approved_by',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferReversalItem::class, 'stock_transfer_reversal_id');
    }
}

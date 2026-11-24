<?php

namespace Ongudidan\FortuneCore\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;
use Ongudidan\FortuneCore\Modules\Transfers\Models\PaymentAccountTransfer;

class PaymentTransferDenomination extends BaseModel
{
    protected $baseTable = 'payment_transfer_denominations';

    protected $fillable = [
        'transfer_id',
        'denomination_id',
        'quantity',
        'received_quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountTransfer::class, 'transfer_id');
    }

    public function denomination(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodDenomination::class, 'denomination_id');
    }
}

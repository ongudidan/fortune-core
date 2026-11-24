<?php

namespace Ongudidan\FortuneCore\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;
use Ongudidan\FortuneCore\Modules\Transfers\Models\PaymentTransaction;

class PaymentTransactionDenomination extends BaseModel
{
    protected $baseTable = 'payment_transaction_denominations';

    protected $fillable = [
        'payment_transaction_id',
        'denomination_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function denomination(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodDenomination::class, 'denomination_id');
    }
}

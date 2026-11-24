<?php

namespace Ongudidan\FortuneCore\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class PaymentAccountDenomination extends BaseModel
{
    protected $baseTable = 'payment_account_denominations';

    protected $fillable = [
        'payment_account_id',
        'denomination_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function denomination(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodDenomination::class, 'denomination_id');
    }

    public function cashDenomination(): BelongsTo
    {
        return $this->belongsTo(CashDenomination::class, 'denomination_id');
    }
}

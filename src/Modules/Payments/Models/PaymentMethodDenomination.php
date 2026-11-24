<?php

namespace Ongudidan\FortuneCore\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class PaymentMethodDenomination extends BaseModel
{
    protected $baseTable = 'payment_method_denominations';

    protected $fillable = [
        'payment_method_id',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}

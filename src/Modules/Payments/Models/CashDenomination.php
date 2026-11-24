<?php

namespace Ongudidan\FortuneCore\Modules\Payments\Models;

use Ongudidan\FortuneCore\Models\BaseModel;

class CashDenomination extends BaseModel
{
    protected $baseTable = 'cash_denominations';

    protected $fillable = [
        'type', // bill, coin
        'value',
        'currency',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];
}

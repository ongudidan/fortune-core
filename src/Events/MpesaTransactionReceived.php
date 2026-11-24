<?php

namespace Ongudidan\FortuneCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\FortuneCore\Modules\Mpesa\Models\MpesaTransaction;

class MpesaTransactionReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public MpesaTransaction $transaction) {}
}

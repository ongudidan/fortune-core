<?php

namespace Ongudidan\FortuneCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\FortuneCore\Modules\Transfers\Models\PaymentAccountTransfer;

class PaymentAccountTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentAccountTransfer $transfer) {}
}

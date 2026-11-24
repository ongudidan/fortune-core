<?php

namespace Ongudidan\FortuneCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\FortuneCore\Modules\Wallets\Models\CustomerWalletTransaction;

class CustomerWalletDebited
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomerWalletTransaction $walletTransaction) {}
}

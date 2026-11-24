<?php

namespace Ongudidan\FortuneCore\Enums;

enum MpesaTransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}

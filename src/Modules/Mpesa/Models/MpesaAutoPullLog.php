<?php

namespace Ongudidan\FortuneCore\Modules\Mpesa\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;

class MpesaAutoPullLog extends BaseModel
{
    protected $baseTable = 'mpesa_auto_pull_logs';

    protected $fillable = [
        'payment_account_id',
        'status',
        'pulled_count',
        'matched_count',
        'unmatched_count',
        'message',
        'details',
    ];

    protected $casts = [
        'pulled_count' => 'integer',
        'matched_count' => 'integer',
        'unmatched_count' => 'integer',
        'details' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Mpesa\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;

class MpesaPullRequest extends BaseModel
{
    protected $baseTable = 'mpesa_pull_requests';

    protected $fillable = [
        'payment_account_id',
        'request_id',
        'status',
        'from_time',
        'to_time',
        'transactions_count',
        'response_payload',
    ];

    protected $casts = [
        'from_time' => 'datetime',
        'to_time' => 'datetime',
        'transactions_count' => 'integer',
        'response_payload' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function pullTransactions(): HasMany
    {
        return $this->hasMany(MpesaPullTransaction::class, 'mpesa_pull_request_id');
    }
}

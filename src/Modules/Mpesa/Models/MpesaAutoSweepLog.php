<?php

namespace Ongudidan\FortuneCore\Modules\Mpesa\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;

class MpesaAutoSweepLog extends BaseModel
{
    protected $baseTable = 'mpesa_auto_sweep_logs';

    protected $fillable = [
        'payment_account_id',
        'auto_sweep_run_id',
        'status',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MpesaAutoSweepRun::class, 'auto_sweep_run_id');
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SmsConfig extends BaseModel
{
    protected $baseTable = 'sms_configs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sms_provider_id',
        'sender_id', // also used as shortcode for TextSMS
        'api_key',
        'partner_id',
        'alert_threshold',
        'is_active',
    ];

    protected $casts = [
        'alert_threshold' => 'integer',
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function smsProvider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class);
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SmsLog extends BaseModel
{
    protected $baseTable = 'sms_logs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sms_config_id',
        'sms_template_id',
        'system_sms_template_id',
        'sms_campaign_id',
        'phone_number',
        'network',
        'message',
        'message_id',
        'status',
        'delivery_status',
        'provider_response',
        'cost',
    ];

    protected $casts = [
        'provider_response' => 'array',
    ];

    public static function mapNetworkId(int $networkId): string
    {
        return [
            1 => 'Safaricom',
            2 => 'Airtel',
            3 => 'Telkom',
            5 => 'Equitel',
        ][$networkId] ?? 'Unknown';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function smsConfig(): BelongsTo
    {
        return $this->belongsTo(SmsConfig::class);
    }

    public function smsTemplate(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class);
    }

    public function systemSmsTemplate(): BelongsTo
    {
        return $this->belongsTo(SystemSmsTemplate::class);
    }

    public function smsCampaign(): BelongsTo
    {
        return $this->belongsTo(SmsCampaign::class);
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class SmsCampaign extends BaseModel
{
    protected $baseTable = 'sms_campaigns';

    protected $fillable = [
        'company_id', 'branch_id', 'sms_config_id', 'sms_template_id',
        'title', 'message',
        'total_recipients', 'sent_count', 'delivered_count', 'failed_count',
        'status', 'is_scheduled', 'scheduled_at',
    ];

    protected $casts = [
        'is_scheduled' => 'boolean',
        'scheduled_at' => 'datetime',
    ];

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

    public function logs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }
}

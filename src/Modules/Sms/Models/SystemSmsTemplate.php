<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SystemSmsTemplate extends BaseModel
{
    protected $baseTable = 'system_sms_templates';

    protected $fillable = [
        'company_id',
        'event_type',
        'content',
        'is_active',
        'schedule_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'schedule_hours' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

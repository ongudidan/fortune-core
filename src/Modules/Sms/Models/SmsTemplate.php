<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class SmsTemplate extends BaseModel
{
    protected $baseTable = 'sms_templates';

    protected $fillable = [
        'company_id',
        'name',
        'content',
        'trigger_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

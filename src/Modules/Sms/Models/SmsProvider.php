<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class SmsProvider extends BaseModel
{
    protected $baseTable = 'sms_providers';

    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function smsConfigs(): HasMany
    {
        return $this->hasMany(SmsConfig::class);
    }
}

<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;

class UpdateSmsDeliveryStatusJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?int $smsLogId = null,
        public ?int $smsCampaignId = null,
        public int $limit = 100,
    ) {}

    public function handle(SmsService $smsService): void
    {
        // Job disabled completely. Delivery status checks are performed manually.
    }
}

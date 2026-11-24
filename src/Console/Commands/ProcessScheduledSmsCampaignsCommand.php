<?php

namespace Ongudidan\FortuneCore\Console\Commands;

use Illuminate\Console\Command;
use Ongudidan\FortuneCore\Modules\Sms\Jobs\ProcessSmsCampaign;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsCampaign;

class ProcessScheduledSmsCampaignsCommand extends Command
{
    protected $signature = 'fortune:sms-process-scheduled';

    protected $description = 'Dispatch scheduled SMS campaigns whose scheduled time has arrived';

    public function handle(): int
    {
        $due = SmsCampaign::where('is_scheduled', true)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No due scheduled SMS campaigns.');

            return self::SUCCESS;
        }

        foreach ($due as $campaign) {
            $campaign->update(['status' => 'processing']);
            ProcessSmsCampaign::dispatch($campaign);
            $this->info("Dispatched campaign #{$campaign->id}: {$campaign->title}");
        }

        return self::SUCCESS;
    }
}

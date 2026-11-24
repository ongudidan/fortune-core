<?php

namespace Ongudidan\FortuneCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsLog;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;

class UpdateSmsDeliveryStatusCommand extends Command
{
    protected $signature = 'fortune:sms-update-status {--limit=100 : Max logs to process per run}';

    protected $description = 'Check delivery status of recently sent SMS messages and update logs';

    public function handle(SmsService $smsService): int
    {
        $limit = (int) $this->option('limit');

        $logs = SmsLog::with('smsConfig')
            ->whereIn('delivery_status', ['pending', 'sent'])
            ->whereNotNull('message_id')
            ->whereNotNull('sms_config_id')
            ->where('created_at', '>=', now()->subHours(48))
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No pending SMS logs to check.');

            return self::SUCCESS;
        }

        $total = $logs->count();
        $this->info("Checking delivery status for {$total} message(s)...");

        $updated = 0;
        $delivered = 0;
        $failed = 0;

        foreach ($logs as $index => $log) {
            $oldStatus = $log->delivery_status;
            $newStatus = $smsService->updateDeliveryStatus($log);

            if ($newStatus !== $oldStatus) {
                $updated++;
                if ($newStatus === 'delivered') {
                    $delivered++;
                }
                if ($newStatus === 'failed') {
                    $failed++;
                }
            }
        }

        $summary = "SMS Delivery Status: Processed {$total} | Updated {$updated} (Delivered: {$delivered}, Failed: {$failed})";
        $this->info($summary);
        if ($updated > 0) {
            Log::info($summary);
        }

        return self::SUCCESS;
    }
}

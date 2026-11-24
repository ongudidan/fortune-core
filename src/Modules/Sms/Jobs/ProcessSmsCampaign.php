<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsCampaign;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsLog;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;

class ProcessSmsCampaign implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600; // 1 hour for large campaigns

    public $tries = 1;

    public function __construct(protected SmsCampaign $campaign) {}

    public function handle(SmsService $smsService): void
    {
        Log::info("BulkSMS: Starting campaign #{$this->campaign->id} — {$this->campaign->total_recipients} recipients");
        $this->campaign->update(['status' => 'processing']);

        $batchSize = 100;
        $sentCount = 0;
        $failedCount = 0;

        $this->campaign->logs()
            ->where('status', 'pending')
            ->chunk($batchSize, function ($logs) use ($smsService, &$sentCount, &$failedCount) {

                // Build comma-separated number list for the batch API call
                $mobileNumbers = $logs
                    ->pluck('phone_number')
                    ->map(fn ($n) => $smsService->formatNumber($n))
                    ->implode(',');

                try {
                    $response = $smsService->sendBulk(
                        $this->campaign->smsConfig->partner_id,
                        $this->campaign->smsConfig->api_key,
                        $this->campaign->smsConfig->sender_id,
                        $mobileNumbers,
                        $this->campaign->message
                    );

                    $responses = $response['responses'] ?? [];
                    $responseMap = [];
                    foreach ($responses as $res) {
                        if (isset($res['mobile'])) {
                            $key = substr(preg_replace('/\D/', '', (string) $res['mobile']), -9);
                            $responseMap[$key] = $res;
                        }
                    }

                    foreach ($logs as $log) {
                        $logKey = substr(preg_replace('/\D/', '', (string) $log->phone_number), -9);
                        $resData = $responseMap[$logKey] ?? null;

                        if ($resData) {
                            $network = isset($resData['networkid'])
                                ? SmsLog::mapNetworkId((int) $resData['networkid'])
                                : null;

                            $ok = ($resData['response-code'] ?? null) == 200;
                            $log->update([
                                'network' => $network,
                                'message_id' => $resData['messageid'] ?? null,
                                'status' => $ok ? 'sent' : 'failed',
                                'delivery_status' => 'pending',
                                'provider_response' => $response,
                            ]);

                            $ok ? $sentCount++ : $failedCount++;
                        } else {
                            $log->update(['status' => 'failed', 'delivery_status' => 'failed']);
                            $failedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("BulkSMS: Batch error for campaign #{$this->campaign->id}: ".$e->getMessage());
                    foreach ($logs as $log) {
                        $log->update(['status' => 'failed', 'delivery_status' => 'failed']);
                        $failedCount++;
                    }
                }

                usleep(200_000); // 200ms throttle between batches
            });

        $this->campaign->update([
            'status' => 'completed',
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        Log::info("BulkSMS: Campaign #{$this->campaign->id} completed — sent:{$sentCount} failed:{$failedCount}");
    }
}

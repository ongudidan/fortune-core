<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Contracts\SmsGatewayInterface;
use Ongudidan\FortuneCore\Modules\Sms\Drivers\TextSmsDriver;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsConfig;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsLog;

class SmsService
{
    public function __construct(
        protected ?SmsGatewayInterface $gateway = null
    ) {
        $this->gateway = $gateway ?? new TextSmsDriver;
    }

    /**
     * Send SMS using raw credentials (used by Jobs for single SMS).
     */
    public function sendRaw(string $partnerId, string $apiKey, string $shortcode, string $mobile, string $message): array
    {
        return $this->gateway->send($partnerId, $apiKey, $shortcode, $this->formatNumber($mobile), $message);
    }

    /**
     * Send bulk SMS to a comma-separated string of ALREADY-FORMATTED numbers.
     * Do NOT call formatNumber() here — the numbers are pre-formatted by the caller.
     */
    public function sendBulk(string $partnerId, string $apiKey, string $shortcode, string $formattedMobiles, string $message): array
    {
        return $this->gateway->sendBulk($partnerId, $apiKey, $shortcode, $formattedMobiles, $message);
    }

    /**
     * Send an SMS using an SmsConfig object (used by high-level callers).
     */
    public function send(SmsConfig $config, string $mobile, string $message, ?int $templateId = null): ?SmsLog
    {
        try {
            $response = $this->sendRaw(
                $config->partner_id,
                $config->api_key,
                $config->sender_id,
                $mobile,
                $message
            );

            $resData = $response['responses'][0] ?? null;
            $networkName = isset($resData['networkid']) ? SmsLog::mapNetworkId($resData['networkid']) : null;

            // Invalidate cached balance after sending
            Cache::forget("sms_balance_config_{$config->id}");

            return SmsLog::create([
                'company_id' => $config->company_id,
                'branch_id' => $config->branch_id,
                'sms_config_id' => $config->id,
                'sms_template_id' => $templateId,
                'phone_number' => $this->formatNumber($mobile),
                'network' => $networkName,
                'message' => $message,
                'message_id' => $resData['messageid'] ?? null,
                'status' => ($resData['response-code'] ?? null) == 200 ? 'sent' : 'failed',
                'delivery_status' => 'pending',
                'provider_response' => $response,
                'cost' => $response['cost'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SMS Send Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch live balance directly from the SMS provider API.
     * No caching — always returns fresh data.
     */
    public function getBalance(SmsConfig $config): float
    {
        try {
            $response = $this->gateway->checkBalance($config->partner_id, $config->api_key);
            $balance = (float) ($response['credit'] ?? $response ?? 0);
            Cache::put("sms_balance_config_{$config->id}", $balance, now()->addMinutes(10));

            return $balance;
        } catch (\Exception $e) {
            Log::error("SMS Balance API Error (Config #{$config->id}): ".$e->getMessage());

            return 0;
        }
    }

    /**
     * Check if any active SMS configuration for a company (or all active configs) has a balance below its alert threshold.
     * Caches the result to prevent high-frequency external API calls on page loads.
     */
    public function getActiveConfigLowBalanceAlert(?int $companyId = null, bool $forceRefresh = false): ?array
    {
        try {
            $query = SmsConfig::with(['smsProvider', 'company', 'branch'])
                ->where('is_active', true);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $activeConfigs = $query->get();

            foreach ($activeConfigs as $config) {
                $threshold = (float) ($config->alert_threshold ?? 100);

                // If threshold is 0 or negative, alerting is disabled for this config
                if ($threshold <= 0) {
                    continue;
                }

                $cacheKey = "sms_balance_config_{$config->id}";
                if ($forceRefresh) {
                    Cache::forget($cacheKey);
                }

                // Cache balance for 10 minutes to avoid latency on navigation
                $balance = Cache::remember(
                    $cacheKey,
                    now()->addMinutes(10),
                    fn () => $this->getBalance($config)
                );

                if ($balance <= $threshold) {
                    return [
                        'has_alert' => true,
                        'config_id' => $config->id,
                        'balance' => $balance,
                        'threshold' => $threshold,
                        'provider_name' => $config->smsProvider?->name ?? 'SMS Gateway',
                        'sender_id' => $config->sender_id,
                        'company_name' => $config->company?->name,
                        'branch_name' => $config->branch?->name,
                        'message' => "SMS balance is low: {$balance} units remaining (Alert threshold: {$threshold})",
                    ];
                }
            }

            return [
                'has_alert' => false,
            ];
        } catch (\Throwable $e) {
            Log::warning('SMS low balance alert check error: '.$e->getMessage());

            return [
                'has_alert' => false,
            ];
        }
    }

    /**
     * Check delivery status of a sent message via provider API.
     */
    public function status(string $partnerId, string $apiKey, string $messageId): array
    {
        try {
            $response = Http::timeout(5)->post('https://sms.textsms.co.ke/api/services/getdlr/', [
                'partnerID' => $partnerId,
                'apikey' => $apiKey,
                'messageID' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception $e) {
            Log::warning("SMS Status API Timeout/Error (Message ID {$messageId}): ".$e->getMessage());
        }

        try {
            return $this->gateway->checkDeliveryStatus($partnerId, $apiKey, $messageId);
        } catch (\Exception $e) {
            return ['response-code' => 500, 'response-description' => $e->getMessage()];
        }
    }

    /**
     * Resolve and update delivery status for a single SmsLog record.
     * Returns the new status string.
     */
    public function updateDeliveryStatus(\App\Models\SmsLog $log): string
    {
        if (! $log->message_id || ! $log->smsConfig) {
            return $log->delivery_status;
        }

        try {
            $response = $this->status(
                $log->smsConfig->partner_id,
                $log->smsConfig->api_key,
                $log->message_id
            );

            if (is_array($response) && isset($response['response-code']) && $response['response-code'] == 200) {
                $description = $response['delivery-description'] ?? '';

                $newStatus = match (true) {
                    in_array($description, ['DeliveredToTerminal', 'SentToNetwork']) => 'delivered',
                    $description === 'Unknown' => 'failed',
                    default => $log->delivery_status,
                };

                // Backfill network if missing
                $network = $log->network;
                if (! $network && isset($response['delivery-networkid'])) {
                    $network = \App\Models\SmsLog::mapNetworkId((int) $response['delivery-networkid']);
                }

                $existingResponse = is_array($log->provider_response)
                    ? $log->provider_response
                    : (is_string($log->provider_response) ? (json_decode($log->provider_response, true) ?: []) : []);

                $log->update([
                    'delivery_status' => $newStatus,
                    'network' => $network,
                    'provider_response' => array_merge(
                        $existingResponse,
                        ['status_update' => $response]
                    ),
                ]);

                return $newStatus;
            }
        } catch (\Throwable $e) {
            Log::error("SMS Status Check Error (Log #{$log->id}): ".$e->getMessage());
        }

        return $log->delivery_status;
    }

    /**
     * Normalize phone number to 254XXXXXXXXX format.
     */
    public function formatNumber(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);

        if (str_starts_with($number, '0')) {
            return '254'.substr($number, 1);
        }

        if (str_starts_with($number, '7') || str_starts_with($number, '1')) {
            return '254'.$number;
        }

        if (str_starts_with($number, '+')) {
            return substr($number, 1);
        }

        return $number;
    }
}

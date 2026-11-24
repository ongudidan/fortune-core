<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Drivers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Contracts\SmsGatewayInterface;

class TextSmsDriver implements SmsGatewayInterface
{
    protected string $baseUrl = 'https://sms.textsms.co.ke/api/services';

    public function send(string $partnerId, string $apiKey, string $shortcode, string $mobile, string $message): array
    {
        return $this->post('/sendsms/', [
            'partnerID' => $partnerId,
            'apikey' => $apiKey,
            'shortcode' => $shortcode,
            'mobile' => $mobile,
            'message' => $message,
        ]);
    }

    public function sendBulk(string $partnerId, string $apiKey, string $shortcode, string $formattedMobiles, string $message): array
    {
        return $this->post('/sendsms/', [
            'partnerID' => $partnerId,
            'apikey' => $apiKey,
            'shortcode' => $shortcode,
            'mobile' => $formattedMobiles,
            'message' => $message,
        ]);
    }

    public function checkBalance(string $partnerId, string $apiKey): array
    {
        return $this->post('/getbalance/', [
            'partnerID' => $partnerId,
            'apikey' => $apiKey,
        ]);
    }

    public function checkDeliveryStatus(string $partnerId, string $apiKey, string $messageId): array
    {
        return $this->post('/getdlr/', [
            'partnerID' => $partnerId,
            'apikey' => $apiKey,
            'messageID' => $messageId,
        ]);
    }

    protected function post(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl.$endpoint, $data);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('TextSMS API returned unsuccessful status: '.$response->status(), [
                'body' => $response->body(),
            ]);

            return [
                'response-code' => $response->status(),
                'response-description' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('TextSMS network exception: '.$e->getMessage());

            return [
                'response-code' => 500,
                'response-description' => $e->getMessage(),
            ];
        }
    }
}

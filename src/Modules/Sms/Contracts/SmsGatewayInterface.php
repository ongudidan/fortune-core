<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Contracts;

interface SmsGatewayInterface
{
    /**
     * Send a single SMS to a recipient.
     */
    public function send(string $partnerId, string $apiKey, string $shortcode, string $mobile, string $message): array;

    /**
     * Send bulk SMS to comma-separated recipients.
     */
    public function sendBulk(string $partnerId, string $apiKey, string $shortcode, string $formattedMobiles, string $message): array;

    /**
     * Check account SMS balance.
     */
    public function checkBalance(string $partnerId, string $apiKey): array;

    /**
     * Check message delivery status.
     */
    public function checkDeliveryStatus(string $partnerId, string $apiKey, string $messageId): array;
}

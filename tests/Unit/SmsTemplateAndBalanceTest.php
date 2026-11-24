<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Ongudidan\FortuneCore\Modules\Sms\Contracts\SmsGatewayInterface;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsConfig;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;
use Ongudidan\FortuneCore\Modules\Sms\Services\SystemSmsService;
use Ongudidan\FortuneCore\Tests\TestCase;

class SmsTemplateAndBalanceTest extends TestCase
{
    public function test_system_sms_template_variable_substitution(): void
    {
        $service = app(SystemSmsService::class);

        $template = 'Dear {customer_name}, invoice #{invoice_number} of KES {amount} is confirmed.';
        $data = [
            'customer_name' => 'John Kamau',
            'invoice_number' => 'INV-2026-0042',
            'amount' => '15,000.00',
        ];

        $rendered = $service->substituteVariables($template, $data);

        $expected = 'Dear John Kamau, invoice #INV-2026-0042 of KES 15,000.00 is confirmed.';
        $this->assertEquals($expected, $rendered);
    }

    public function test_sms_balance_caching_and_invalidation(): void
    {
        // Mock the gateway
        $mockGateway = new class implements SmsGatewayInterface
        {
            public int $balanceCalls = 0;

            public function send(string $p, string $a, string $s, string $m, string $msg): array
            {
                return ['responses' => [['response-code' => 200, 'messageid' => 'MSG123', 'networkid' => '1']]];
            }

            public function sendBulk(string $p, string $a, string $s, string $m, string $msg): array
            {
                return [];
            }

            public function checkBalance(string $p, string $a): array
            {
                $this->balanceCalls++;

                return ['credit' => 450.50];
            }

            public function checkDeliveryStatus(string $p, string $a, string $m): array
            {
                return [];
            }
        };

        $service = new SmsService($mockGateway);

        $config = SmsConfig::create([
            'partner_id' => '101',
            'api_key' => 'secret_key',
            'sender_id' => 'FORTUNE',
            'alert_threshold' => 100,
            'is_active' => true,
        ]);

        // First call fetches from gateway
        $balance = $service->getBalance($config);
        $this->assertEquals(450.50, $balance);
        $this->assertEquals(1, $mockGateway->balanceCalls);

        // Cached value exists
        $this->assertEquals(450.50, Cache::get("sms_balance_config_{$config->id}"));

        // Sending an SMS invalidates the cached balance
        $service->send($config, '0712345678', 'Test message');
        $this->assertNull(Cache::get("sms_balance_config_{$config->id}"));
    }
}

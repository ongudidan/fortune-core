<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ongudidan\FortuneCore\Modules\Mpesa\Models\MpesaConfig;
use Ongudidan\FortuneCore\Modules\Mpesa\Models\MpesaTransaction;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaTransactionService;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;
use Ongudidan\FortuneCore\Modules\Wallets\Services\WalletService;
use Ongudidan\FortuneCore\Tests\TestCase;

class MpesaTierMatchingTest extends TestCase
{
    protected PaymentAccount $account;

    protected MpesaConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = PaymentAccount::create([
            'name' => 'Main Till',
            'type' => 'mpesa_till',
            'account_number' => '174379',
            'currency' => 'KES',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->config = MpesaConfig::create([
            'payment_account_id' => $this->account->id,
            'name' => 'Till 174379',
            'shortcode' => '174379',
            'type' => 'till',
            'consumer_key' => 'test_consumer_key',
            'consumer_secret' => 'test_consumer_secret',
            'passkey' => 'test_passkey',
            'environment' => 'sandbox',
            'is_active' => true,
        ]);
    }

    public function test_tier3_fallback_credits_unallocated_suspense_account(): void
    {
        $service = app(MpesaTransactionService::class);

        $payload = [
            'TransID' => 'QWE1234567',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'UNKNOWN-REF-999',
            'MSISDN' => '254700999999',
            'TransAmount' => 1500.00,
            'FirstName' => 'Jane',
            'LastName' => 'Doe',
        ];

        $transaction = $service->handleC2bConfirmation($payload);

        $this->assertInstanceOf(MpesaTransaction::class, $transaction);
        $this->assertEquals(1500.00, (float) $transaction->amount);
        $this->assertFalse((bool) $transaction->is_matched);
        $this->assertEquals('unallocated', $transaction->allocation_status);

        $this->account->refresh();
        $this->assertEquals(1500.00, (float) $this->account->balance);
    }

    public function test_tier2_customer_phone_match_credits_customer_wallet(): void
    {
        $customerClass = config('fortune.models.customer', 'App\\Models\\Customer');

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->string('telephone')->nullable();
                $table->string('debtor_number')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        $customer = $customerClass::create([
            'name' => 'Alice Mwangi',
            'telephone' => '254711223344',
            'debtor_number' => 'CUST-001',
        ]);

        $service = app(MpesaTransactionService::class);

        $payload = [
            'TransID' => 'ALICE12345',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'CUST-WALLET-TOPUP',
            'MSISDN' => '254711223344',
            'TransAmount' => 3000.00,
            'FirstName' => 'Alice',
            'LastName' => 'Mwangi',
        ];

        $transaction = $service->handleC2bConfirmation($payload);

        $this->assertTrue((bool) $transaction->is_matched);
        $this->assertEquals('allocated', $transaction->allocation_status);

        $walletService = app(WalletService::class);
        $this->assertEquals(3000.00, $walletService->getCustomerBalance($customer->id));
    }
}

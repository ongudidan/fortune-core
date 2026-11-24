<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Modules\Payments\Models\CashDenomination;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;
use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccountDenomination;
use Ongudidan\FortuneCore\Modules\Payments\Services\PaymentAccountService;
use Ongudidan\FortuneCore\Tests\TestCase;

class DenominationHealingTest extends TestCase
{
    public function test_healing_detects_accurate_physical_denomination_match(): void
    {
        $account = PaymentAccount::create([
            'name' => 'Main Cash Vault',
            'type' => 'vault',
            'currency' => 'KES',
            'balance' => 7000.00,
            'has_denominations' => true,
            'is_active' => true,
        ]);

        $denom1000 = CashDenomination::where('value', 1000)->first();
        $denom500 = CashDenomination::where('value', 500)->first();

        // 5 notes of 1000 = 5000, 4 notes of 500 = 2000 => Total 7000
        PaymentAccountDenomination::create([
            'payment_account_id' => $account->id,
            'denomination_id' => $denom1000->id,
            'quantity' => 5,
        ]);

        PaymentAccountDenomination::create([
            'payment_account_id' => $account->id,
            'denomination_id' => $denom500->id,
            'quantity' => 4,
        ]);

        $service = app(PaymentAccountService::class);
        $result = $service->healDenominationBalance($account);

        $this->assertTrue($result['is_reconciled']);
        $this->assertEquals(7000.00, $result['calculated_denomination_balance']);
        $this->assertEquals(7000.00, $result['ledger_balance']);
        $this->assertEquals(0.00, $result['discrepancy']);
    }

    public function test_healing_flags_physical_shortage(): void
    {
        $account = PaymentAccount::create([
            'name' => 'Cash Drawer 1',
            'type' => 'cash_drawer',
            'currency' => 'KES',
            'balance' => 5000.00,
            'has_denominations' => true,
            'is_active' => true,
        ]);

        $denom1000 = CashDenomination::where('value', 1000)->first();

        // Only 3 notes of 1000 = 3000 (Shortage of 2000 against ledger balance 5000)
        PaymentAccountDenomination::create([
            'payment_account_id' => $account->id,
            'denomination_id' => $denom1000->id,
            'quantity' => 3,
        ]);

        $service = app(PaymentAccountService::class);
        $result = $service->healDenominationBalance($account);

        $this->assertFalse($result['is_reconciled']);
        $this->assertEquals(3000.00, $result['calculated_denomination_balance']);
        $this->assertEquals(5000.00, $result['ledger_balance']);
        $this->assertEquals(-2000.00, $result['discrepancy']);
    }
}

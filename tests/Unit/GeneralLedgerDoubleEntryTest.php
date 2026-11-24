<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\Exceptions\UnbalancedJournalException;
use Ongudidan\FortuneCore\Modules\Ledger\Models\JournalEntry;
use Ongudidan\FortuneCore\Modules\Ledger\Services\AccountingService;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;
use Ongudidan\FortuneCore\Modules\Payments\Models\Account;
use Ongudidan\FortuneCore\Tests\TestCase;

class GeneralLedgerDoubleEntryTest extends TestCase
{
    public function test_balanced_journal_entry_posts_successfully(): void
    {
        $accounting = app(AccountingService::class);

        $entry = $accounting->postJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Test POS Cash Receipt',
            'items' => [
                ['account_code' => '1001', 'debit' => 2500.00, 'credit' => 0.00, 'description' => 'Cash received'],
                ['account_code' => '4000', 'debit' => 0.00, 'credit' => 2500.00, 'description' => 'Sales revenue'],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals(2500.00, $entry->total_amount);
        $this->assertCount(2, $entry->items);

        $cash = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4000')->first();

        $this->assertEquals(2500.00, (float) $cash->balance);
        $this->assertEquals(2500.00, (float) $revenue->balance);
    }

    public function test_unbalanced_journal_entry_throws_unbalanced_journal_exception(): void
    {
        $accounting = app(AccountingService::class);

        $this->expectException(UnbalancedJournalException::class);

        $accounting->postJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced Entry',
            'items' => [
                ['account_code' => '1001', 'debit' => 1000.00, 'credit' => 0.00],
                ['account_code' => '4000', 'debit' => 0.00, 'credit' => 800.00],
            ],
        ]);
    }

    public function test_ledger_service_calculates_running_balance(): void
    {
        $ledger = app(LedgerService::class);

        $ledger->postJournal([
            ['account_code' => '1001', 'debit' => 5000.00, 'credit' => 0.00],
            ['account_code' => '1200', 'debit' => 0.00, 'credit' => 5000.00],
        ]);

        $cash = Account::where('code', '1001')->first();
        $this->assertEquals(5000.00, (float) $cash->balance);

        $ledger->postJournal([
            ['account_code' => '1001', 'debit' => 0.00, 'credit' => 2000.00],
            ['account_code' => '6000', 'debit' => 2000.00, 'credit' => 0.00],
        ]);

        $cash->refresh();
        $this->assertEquals(3000.00, (float) $cash->balance);
    }
}

<?php

namespace Ongudidan\FortuneCore\Tests;

use Ongudidan\FortuneCore\Database\Seeders\CashDenominationsSeeder;
use Ongudidan\FortuneCore\Database\Seeders\ChartOfAccountsSeeder;
use Ongudidan\FortuneCore\Database\Seeders\SystemSmsTemplatesSeeder;
use Ongudidan\FortuneCore\FortuneCoreServiceProvider;

if (class_exists(\Orchestra\Testbench\TestCase::class)) {
    abstract class BaseTestParent extends \Orchestra\Testbench\TestCase {}
} elseif (class_exists(\Tests\TestCase::class)) {
    abstract class BaseTestParent extends \Tests\TestCase {}
} else {
    abstract class BaseTestParent extends \PHPUnit\Framework\TestCase {}
}

abstract class TestCase extends BaseTestParent
{
    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'withoutExceptionHandling')) {
            $this->withoutExceptionHandling();
        }

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            FortuneCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('fortune.table_prefix', 'fc_');
    }

    protected function setUpDatabase(): void
    {
        $migrations = [
            __DIR__.'/../database/migrations/2026_01_01_000001_create_fortune_accounts_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000002_create_fortune_denominations_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000003_create_fortune_transactions_and_transfers_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000004_create_fortune_wallets_and_ledgers_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000005_create_fortune_mpesa_tables.php',
            __DIR__.'/../database/migrations/2026_01_01_000006_create_fortune_journal_entries_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000007_create_fortune_cheque_clearances_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000008_create_fortune_cash_drawer_sessions_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000009_create_fortune_sms_tables.php',
        ];

        foreach ($migrations as $migrationFile) {
            if (file_exists($migrationFile)) {
                $migration = require $migrationFile;
                $migration->up();
            }
        }

        (new ChartOfAccountsSeeder)->run();
        (new CashDenominationsSeeder)->run();
        (new SystemSmsTemplatesSeeder)->run();
    }
}

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
        $migrationFiles = glob(__DIR__.'/../database/migrations/*.php');
        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
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

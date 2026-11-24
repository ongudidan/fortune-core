<?php

namespace Ongudidan\FortuneCore;

use Illuminate\Support\ServiceProvider;
use Ongudidan\FortuneCore\Console\Commands\ProcessMpesaAutoPullCommand;
use Ongudidan\FortuneCore\Console\Commands\ProcessMpesaAutoSweepsCommand;
use Ongudidan\FortuneCore\Console\Commands\ProcessScheduledSmsCampaignsCommand;
use Ongudidan\FortuneCore\Console\Commands\UpdateSmsDeliveryStatusCommand;
use Ongudidan\FortuneCore\Modules\Ledger\Services\AccountingService;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaAutoPullService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaAutoSweepService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaConfigService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaPullTransactionService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaSettlementService;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaTransactionService;
use Ongudidan\FortuneCore\Modules\Payments\Services\CashDrawerService;
use Ongudidan\FortuneCore\Modules\Payments\Services\ChequeClearanceService;
use Ongudidan\FortuneCore\Modules\Payments\Services\PaymentAccountService;
use Ongudidan\FortuneCore\Modules\Reports\Services\BalanceSheetReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\GeneralLedgerReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\MpesaStatementReconciliationService;
use Ongudidan\FortuneCore\Modules\Reports\Services\PaymentAnomalyReconciliationService;
use Ongudidan\FortuneCore\Modules\Reports\Services\ProfitLossReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\PurchasePaymentReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\SellPaymentReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\TrialBalanceReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\UnallocatedSuspenseReportService;
use Ongudidan\FortuneCore\Modules\Reports\Services\VaultAuditReportService;
use Ongudidan\FortuneCore\Modules\Sms\Contracts\SmsGatewayInterface;
use Ongudidan\FortuneCore\Modules\Sms\Drivers\TextSmsDriver;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;
use Ongudidan\FortuneCore\Modules\Sms\Services\SystemSmsService;
use Ongudidan\FortuneCore\Modules\Transfers\Services\PaymentAccountTransferService;
use Ongudidan\FortuneCore\Modules\Wallets\Services\CustomerWalletLedgerService;
use Ongudidan\FortuneCore\Modules\Wallets\Services\WalletService;
use Ongudidan\Multi2fa\Multi2faServiceProvider;

class FortuneCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services and bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/fortune.php',
            'fortune'
        );

        // 1. General Ledger Module
        if (config('fortune.modules.ledger', true)) {
            $this->app->singleton(LedgerService::class, fn () => new LedgerService);
            $this->app->singleton(AccountingService::class, fn () => new AccountingService);
            $this->app->alias(LedgerService::class, 'fortune.ledger');
            $this->app->alias(AccountingService::class, 'fortune.accounting');
        }

        // 2. Payments & Cash Vaults Module
        if (config('fortune.modules.payments', true)) {
            $this->app->singleton(PaymentAccountService::class, function ($app) {
                return new PaymentAccountService($app->make(LedgerService::class));
            });
            $this->app->singleton(ChequeClearanceService::class, function ($app) {
                return new ChequeClearanceService($app->make(LedgerService::class));
            });
            $this->app->singleton(CashDrawerService::class, function ($app) {
                return new CashDrawerService($app->make(PaymentAccountTransferService::class));
            });
            $this->app->alias(PaymentAccountService::class, 'fortune.payments');
            $this->app->alias(ChequeClearanceService::class, 'fortune.cheques');
            $this->app->alias(CashDrawerService::class, 'fortune.drawers');
        }

        // 3. Wallets Module
        if (config('fortune.modules.wallets', true)) {
            $this->app->singleton(WalletService::class, function ($app) {
                return new WalletService($app->make(LedgerService::class));
            });
            $this->app->singleton(CustomerWalletLedgerService::class, fn () => new CustomerWalletLedgerService);
            $this->app->alias(WalletService::class, 'fortune.wallets');
        }

        // 4. Inter-Account Transfers Module
        if (config('fortune.modules.transfers', true)) {
            $this->app->singleton(PaymentAccountTransferService::class, function ($app) {
                return new PaymentAccountTransferService($app->make(LedgerService::class));
            });
            $this->app->alias(PaymentAccountTransferService::class, 'fortune.transfers');
        }

        // 5. M-Pesa Daraja Automation Module
        if (config('fortune.modules.mpesa', true)) {
            $this->app->singleton(MpesaConfigService::class, fn () => new MpesaConfigService);
            $this->app->singleton(MpesaTransactionService::class, function ($app) {
                return new MpesaTransactionService(
                    $app->make(LedgerService::class),
                    $app->make(WalletService::class),
                    $app->make(MpesaConfigService::class)
                );
            });
            $this->app->singleton(MpesaSettlementService::class, function ($app) {
                return new MpesaSettlementService(
                    $app->make(LedgerService::class),
                    $app->make(WalletService::class)
                );
            });
            $this->app->singleton(MpesaAutoSweepService::class, function ($app) {
                return new MpesaAutoSweepService($app->make(PaymentAccountTransferService::class));
            });
            $this->app->singleton(MpesaPullTransactionService::class, function ($app) {
                return new MpesaPullTransactionService(
                    $app->make(MpesaTransactionService::class),
                    $app->make(MpesaSettlementService::class)
                );
            });
            $this->app->singleton(MpesaAutoPullService::class, function ($app) {
                return new MpesaAutoPullService($app->make(MpesaPullTransactionService::class));
            });
            $this->app->alias(MpesaTransactionService::class, 'fortune.mpesa');
        }

        // 6. Bulk & System SMS Engine
        if (config('fortune.modules.sms', true)) {
            $this->app->bind(SmsGatewayInterface::class, TextSmsDriver::class);
            $this->app->singleton(SmsService::class, function ($app) {
                return new SmsService($app->make(SmsGatewayInterface::class));
            });
            $this->app->singleton(SystemSmsService::class, function ($app) {
                return new SystemSmsService($app->make(SmsService::class));
            });
            $this->app->alias(SmsService::class, 'fortune.sms');
            $this->app->alias(SystemSmsService::class, 'fortune.system-sms');
        }

        // 7. Financial & Reconciliation Reports
        if (config('fortune.modules.reports', true)) {
            $this->app->singleton(ProfitLossReportService::class, fn () => new ProfitLossReportService);
            $this->app->singleton(PurchasePaymentReportService::class, fn () => new PurchasePaymentReportService);
            $this->app->singleton(SellPaymentReportService::class, fn () => new SellPaymentReportService);
            $this->app->singleton(VaultAuditReportService::class, function ($app) {
                return new VaultAuditReportService($app->make(PaymentAccountService::class));
            });
            $this->app->singleton(UnallocatedSuspenseReportService::class, fn () => new UnallocatedSuspenseReportService);
            $this->app->singleton(TrialBalanceReportService::class, fn () => new TrialBalanceReportService);
            $this->app->singleton(BalanceSheetReportService::class, fn () => new BalanceSheetReportService);
            $this->app->singleton(GeneralLedgerReportService::class, fn () => new GeneralLedgerReportService);
            $this->app->singleton(MpesaStatementReconciliationService::class, fn () => new MpesaStatementReconciliationService);
            $this->app->singleton(PaymentAnomalyReconciliationService::class, fn () => new PaymentAnomalyReconciliationService);
        }

        // 8. Two-Factor Authentication Module (Powered by ongudidan/laravel-multi-2fa)
        if (config('fortune.modules.two_factor', true) && class_exists(Multi2faServiceProvider::class)) {
            $this->app->register(Multi2faServiceProvider::class);
        }
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load Routes
        if (file_exists(__DIR__.'/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
        if (file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        // Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fortune');

        // Console publishing and commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessMpesaAutoSweepsCommand::class,
                ProcessMpesaAutoPullCommand::class,
                ProcessScheduledSmsCampaignsCommand::class,
                UpdateSmsDeliveryStatusCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/fortune.php' => config_path('fortune.php'),
            ], 'fortune-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'fortune-migrations');

            $this->publishes([
                __DIR__.'/../database/seeders' => database_path('seeders'),
            ], 'fortune-seeders');

            $this->publishes([
                __DIR__.'/../resources/js/Components' => resource_path('js/components/fortune'),
                __DIR__.'/../resources/js/Pages' => resource_path('js/pages/fortune'),
            ], 'fortune-vue');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fortune'),
            ], 'fortune-views');
        }
    }
}

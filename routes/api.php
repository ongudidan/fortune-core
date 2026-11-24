<?php

use Illuminate\Support\Facades\Route;
use Ongudidan\FortuneCore\Http\Controllers\MpesaConfigController;
use Ongudidan\FortuneCore\Http\Controllers\MpesaTransactionController;
use Ongudidan\FortuneCore\Http\Controllers\MpesaWebhookController;
use Ongudidan\FortuneCore\Http\Controllers\PaymentAccountTransferController;
use Ongudidan\FortuneCore\Http\Controllers\SmsController;

Route::prefix(config('fortune.routes.prefix', 'api/v1/fortune'))
    ->middleware(config('fortune.routes.middleware', ['api']))
    ->group(function () {

        // M-Pesa Module Routes
        if (config('fortune.modules.mpesa', true)) {
            Route::post('/mpesa/stk-push', [MpesaTransactionController::class, 'stkPush'])->name('fortune.mpesa.stk-push');
            Route::get('/mpesa/stk-query', [MpesaTransactionController::class, 'queryStatus'])->name('fortune.mpesa.stk-query');

            Route::post('/mpesa/validation', [MpesaWebhookController::class, 'validation'])->name('fortune.mpesa.validation');
            Route::post('/mpesa/confirmation', [MpesaWebhookController::class, 'confirmation'])->name('fortune.mpesa.confirmation');
            Route::post('/mpesa/stk-callback', [MpesaWebhookController::class, 'stkCallback'])->name('fortune.mpesa.stk-callback');

            Route::get('/mpesa/configs', [MpesaConfigController::class, 'index'])->name('fortune.mpesa.configs.index');
            Route::post('/mpesa/configs', [MpesaConfigController::class, 'store'])->name('fortune.mpesa.configs.store');
            Route::put('/mpesa/configs/{config}', [MpesaConfigController::class, 'update'])->name('fortune.mpesa.configs.update');
            Route::delete('/mpesa/configs/{config}', [MpesaConfigController::class, 'destroy'])->name('fortune.mpesa.configs.destroy');
            Route::post('/mpesa/configs/{config}/toggle-active', [MpesaConfigController::class, 'toggleActive'])->name('fortune.mpesa.configs.toggle-active');
            Route::post('/mpesa/configs/{config}/register-urls', [MpesaConfigController::class, 'registerUrls'])->name('fortune.mpesa.configs.register-urls');
        }

        // Inter-Account Transfers Module Routes
        if (config('fortune.modules.transfers', true)) {
            Route::post('/transfers', [PaymentAccountTransferController::class, 'initiate'])->name('fortune.transfers.initiate');
            Route::post('/transfers/{transfer}/approve', [PaymentAccountTransferController::class, 'approve'])->name('fortune.transfers.approve');
            Route::post('/transfers/{transfer}/reject', [PaymentAccountTransferController::class, 'reject'])->name('fortune.transfers.reject');
        }

        // SMS Module Routes
        if (config('fortune.modules.sms', true)) {
            Route::post('/sms/send', [SmsController::class, 'send'])->name('fortune.sms.send');
            Route::get('/sms/balance', [SmsController::class, 'balance'])->name('fortune.sms.balance');
            Route::get('/sms/templates', [SmsController::class, 'templates'])->name('fortune.sms.templates');
            Route::get('/sms/campaigns', [SmsController::class, 'campaigns'])->name('fortune.sms.campaigns');
        }
    });

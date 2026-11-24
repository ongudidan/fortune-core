<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

return [
    'table_prefix' => env('FORTUNE_TABLE_PREFIX', 'fc_'),

    /*
    |--------------------------------------------------------------------------
    | Active Modules
    |--------------------------------------------------------------------------
    | Toggle modules on/off for zero-bloat operation in client applications.
    */
    'modules' => [
        'two_factor' => env('FORTUNE_2FA_ENABLED', true),
        'payments' => env('FORTUNE_PAYMENTS_ENABLED', true),
        'wallets' => env('FORTUNE_WALLETS_ENABLED', true),
        'transfers' => env('FORTUNE_TRANSFERS_ENABLED', true),
        'mpesa' => env('FORTUNE_MPESA_ENABLED', true),
        'ledger' => env('FORTUNE_LEDGER_ENABLED', true),
        'sms' => env('FORTUNE_SMS_ENABLED', true),
        'reports' => env('FORTUNE_REPORTS_ENABLED', true),
        'inventory' => env('FORTUNE_INVENTORY_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Mapping Registry
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => User::class,
        'customer' => Customer::class,
        'supplier' => Supplier::class,
        'company' => Company::class,
        'branch' => Branch::class,
        'location' => env('FORTUNE_LOCATION_MODEL', 'App\\Models\\Outlet'),
        'product' => env('FORTUNE_PRODUCT_MODEL', 'App\\Models\\Product'),
        'sale' => Sale::class,
        'purchase' => Purchase::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Wholesale Inventory Engine Settings
    |--------------------------------------------------------------------------
    */
    'inventory' => [
        'enable_empty_containers' => env('FORTUNE_INVENTORY_CONTAINERS', true),
        'valuation_method' => env('FORTUNE_INVENTORY_VALUATION', 'fifo'), // fifo, avco
        'default_packaging_unit' => 'Carton',
        'default_base_unit' => 'Piece',
        'accounts' => [
            'inventory_asset' => '1300',
            'cogs' => '5000',
            'shrinkage_expense' => '5500',
            'grni_clearing' => '2050',
        ],
        'low_stock_threshold' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | General Ledger Default Account Mapping
    |--------------------------------------------------------------------------
    */
    'accounts' => [
        'main_vault' => '1001',
        'cash_drawer' => '1002',
        'bank_operating' => '1010',
        'mpesa_till' => '1020',
        'supplier_prepayments' => '1050',
        'accounts_receivable' => '1200',
        'accounts_payable' => '2000',
        'customer_wallet' => '2015',
        'unallocated_suspense' => '2016',
        'sales_revenue' => '4000',
        'cogs' => '5000',
        'inventory_asset' => '1300',
        'shrinkage_expense' => '5500',
        'grni_clearing' => '2050',
        'transaction_charges' => '6000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maker-Checker Transfer Settings
    |--------------------------------------------------------------------------
    */
    'transfers' => [
        'require_approval_above' => 10000.00,
        'max_unapproved_amount' => 10000.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja Configuration
    |--------------------------------------------------------------------------
    */
    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
        'passkey' => env('MPESA_PASSKEY', ''),
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'callback_url' => env('MPESA_CALLBACK_URL', '/api/v1/fortune/mpesa/stk-callback'),
        'callback_url_base' => env('MPESA_CALLBACK_URL_BASE', env('APP_URL')),
        'c2b_secret_token' => env('MPESA_C2B_SECRET_TOKEN'),
        'auto_sweep_enabled' => env('MPESA_AUTO_SWEEP', false),
        'auto_sweep_threshold' => env('MPESA_AUTO_SWEEP_THRESHOLD', 50000.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Configuration (TextSMS & Multi-Provider)
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'default_provider' => env('SMS_PROVIDER', 'textsms'),
        'partner_id' => env('TEXTSMS_PARTNER_ID', ''),
        'api_key' => env('TEXTSMS_API_KEY', ''),
        'sender_id' => env('TEXTSMS_SENDER_ID', ''),
        'alert_threshold' => env('SMS_ALERT_THRESHOLD', 100),
        'test_mode' => env('SMS_TEST_MODE', false),
        'test_phone' => env('SMS_TEST_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'methods' => ['passkey', 'authenticator', 'email'],
        'default_method' => 'email',
        'window' => 1, // TOTP window
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Group Settings
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'api/v1/fortune',
        'middleware' => ['api'],
    ],
];

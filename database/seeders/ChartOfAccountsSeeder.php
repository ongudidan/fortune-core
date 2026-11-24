<?php

namespace Ongudidan\FortuneCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Ongudidan\FortuneCore\Modules\Payments\Models\Account;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => config('fortune.accounts.main_vault', '1001'),
                'name' => 'Main Cash Vault',
                'type' => 'asset',
                'subtype' => 'cash_and_equivalents',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.cash_drawer', '1002'),
                'name' => 'Petty Cash / Cash Drawer',
                'type' => 'asset',
                'subtype' => 'cash_and_equivalents',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.bank_operating', '1010'),
                'name' => 'Operating Bank Account',
                'type' => 'asset',
                'subtype' => 'bank',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.mpesa_till', '1020'),
                'name' => 'M-Pesa Paybill / Till Account',
                'type' => 'asset',
                'subtype' => 'mobile_money',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.supplier_prepayments', '1050'),
                'name' => 'Supplier Advances / Prepayments (Asset)',
                'type' => 'asset',
                'subtype' => 'prepayments',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.accounts_receivable', '1200'),
                'name' => 'Accounts Receivable (AR)',
                'type' => 'asset',
                'subtype' => 'receivables',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.accounts_payable', '2000'),
                'name' => 'Accounts Payable (AP)',
                'type' => 'liability',
                'subtype' => 'payables',
                'normal_balance' => 'credit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.customer_wallet', '2015'),
                'name' => 'Customer Wallet Deposits (Liability)',
                'type' => 'liability',
                'subtype' => 'escrow_liability',
                'normal_balance' => 'credit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.unallocated_suspense', '2016'),
                'name' => 'Unallocated Receipts Suspense (Liability)',
                'type' => 'liability',
                'subtype' => 'suspense',
                'normal_balance' => 'credit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.sales_revenue', '4000'),
                'name' => 'Sales Revenue',
                'type' => 'income',
                'subtype' => 'operating_revenue',
                'normal_balance' => 'credit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.inventory.accounts.inventory_asset', config('fortune.accounts.inventory_asset', '1300')),
                'name' => 'Merchandise Inventory Asset',
                'type' => 'asset',
                'subtype' => 'inventory',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.inventory.accounts.grni_clearing', config('fortune.accounts.grni_clearing', '2050')),
                'name' => 'Goods Received Not Invoiced (GRNI Clearing)',
                'type' => 'liability',
                'subtype' => 'clearing',
                'normal_balance' => 'credit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.inventory.accounts.cogs', config('fortune.accounts.cogs', '5000')),
                'name' => 'Cost of Goods Sold (COGS)',
                'type' => 'expense',
                'subtype' => 'direct_cost',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.inventory.accounts.shrinkage_expense', config('fortune.accounts.shrinkage_expense', '5500')),
                'name' => 'Inventory Shrinkage & Spoilage Loss (Expense)',
                'type' => 'expense',
                'subtype' => 'loss_on_inventory',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'code' => config('fortune.accounts.transaction_charges', '6000'),
                'name' => 'Bank / M-Pesa Transaction Charges (Expense)',
                'type' => 'expense',
                'subtype' => 'financial_expenses',
                'normal_balance' => 'debit',
                'balance' => 0.00,
                'currency' => 'KES',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(
                ['code' => $acc['code']],
                $acc
            );
        }
    }
}

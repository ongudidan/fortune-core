<?php

namespace Ongudidan\FortuneCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Ongudidan\FortuneCore\Modules\Sms\Models\SystemSmsTemplate;

class SystemSmsTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event_type' => 'invoice_created',
                'content' => 'Dear {customer_name}, your order #{invoice_number} of KES {total_amount} has been received and is being processed. Thank you for your business.',
                'is_active' => true,
            ],
            [
                'event_type' => 'invoice_payment',
                'content' => 'Dear {customer_name}, we have received your payment of KES {amount} for Invoice #{invoice_number}. Current balance: KES {balance}. Thank you.',
                'is_active' => true,
            ],
            [
                'event_type' => 'mpesa_payment',
                'content' => 'Confirmed. Payment of KES {amount} received via M-Pesa (Ref: {mpesa_receipt}) from {customer_name} on {date}. Balance: KES {balance}.',
                'is_active' => true,
            ],
            [
                'event_type' => 'reminder',
                'content' => 'Friendly reminder: Dear {customer_name}, Invoice #{invoice_number} of KES {outstanding_balance} is due on {due_date}. Please pay via Paybill {paybill} Acc: {account}.',
                'is_active' => true,
            ],
            [
                'event_type' => 'overdue_invoice',
                'content' => 'Urgent notice: Dear {customer_name}, Invoice #{invoice_number} of KES {outstanding_balance} is overdue. Kindly settle via Paybill {paybill} Acc: {account}.',
                'is_active' => true,
            ],
            [
                'event_type' => 'returns',
                'content' => 'Dear {customer_name}, credit note #{credit_note_number} for KES {amount} has been approved and credited to your account balance.',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tmpl) {
            SystemSmsTemplate::firstOrCreate(
                ['event_type' => $tmpl['event_type']],
                $tmpl
            );
        }
    }
}

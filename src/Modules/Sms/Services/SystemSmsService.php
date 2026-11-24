<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MpesaConfig;
use App\Models\ReturnInward;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Jobs\SendSingleSms;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsConfig;
use Ongudidan\FortuneCore\Modules\Sms\Models\SystemSmsTemplate;

class SystemSmsService
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendSaleNotification(Sale $sale, string $eventType)
    {
        try {
            // Skip SMS for Pay & SelfCollect (Pay Now) sales as payment is received on spot
            if ($sale->delivery_method_id == 1) {
                Log::info("SystemSmsService: Skipped SMS for Pay & SelfCollect sale #{$sale->id}");

                return;
            }

            // CRITICAL DEFENSE: If this is a payment reminder or overdue notice, verify via single source of truth
            $reminderEvents = ['reminder', 'invoice_reminder_24h', 'overdue_invoice', 'invoice_due_reminder'];
            if (in_array($eventType, $reminderEvents)) {
                $balanceService = app(CustomerBalanceService::class);
                if (! $balanceService->isEligibleForPaymentReminder($sale)) {
                    Log::info("SystemSmsService: Skipped reminder '{$eventType}' for sale #{$sale->id} ({$sale->invoice_number}) - not eligible (invoice settled or customer net debt <= 0)");

                    return;
                }
            }

            $sale->load(['branch.company', 'customer.area']);
            $company = $sale->branch?->company;

            if (! $company) {
                Log::warning("SystemSmsService: Sale #{$sale->id} has no associated company.");

                return;
            }

            if (! $company->system_sms_enabled) {
                Log::info("SystemSmsService: System SMS is disabled for company {$company->id} ({$company->name}). Skipping sale #{$sale->id}");

                return;
            }

            $isTestMode = (bool) $company->system_sms_test_mode && ! empty($company->system_sms_test_phone);

            // Don't send SMS to Walk-in or retail customers
            if ($sale->customer && $sale->customer->debtor_number === 'DBT-0001') {
                Log::info("SystemSmsService: Skipped SMS for Walk-in customer on sale #{$sale->id}");

                return;
            }

            if ($sale->customer && $sale->customer->area && $sale->customer->area->is_retail) {
                Log::info("SystemSmsService: Skipped SMS for retail customer on sale #{$sale->id}");

                return;
            }

            $eventAliases = [
                'reminder' => ['reminder', 'invoice_reminder_24h'],
                'invoice_reminder_24h' => ['invoice_reminder_24h', 'reminder'],
                'overdue_invoice' => ['overdue_invoice', 'invoice_due_reminder'],
                'invoice_due_reminder' => ['invoice_due_reminder', 'overdue_invoice'],
            ];

            $template = SystemSmsTemplate::where('company_id', $company->id)
                ->where('event_type', $eventType)
                ->where('is_active', true)
                ->first();

            if (! $template && isset($eventAliases[$eventType])) {
                $fallbackType = $eventAliases[$eventType][1];
                $template = SystemSmsTemplate::where('company_id', $company->id)
                    ->where('event_type', $fallbackType)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $template) {
                Log::warning("SystemSmsService: No active SMS template found for event '{$eventType}' (company: {$company->id})");

                return;
            }

            $config = SmsConfig::where('company_id', $company->id)
                ->where('is_active', true)
                ->where(function ($q) use ($sale) {
                    if ($sale->branch_id) {
                        $q->where('branch_id', $sale->branch_id)
                            ->orWhereNull('branch_id');
                    }
                })
                ->orderByRaw('branch_id IS NOT NULL DESC')
                ->first();

            if (! $config) {
                Log::warning("SystemSmsService: No active SMS config found for company {$company->id}");

                return;
            }

            $recipient = $isTestMode ? $company->system_sms_test_phone : ($sale->customer->telephone ?? '');

            if (empty($recipient) && empty($company->system_sms_copy_phone)) {
                Log::warning("SystemSmsService: No valid phone number found for sale #{$sale->id} (Customer ID: ".($sale->customer_id ?? 'N/A').')');

                return;
            }

            $message = $this->replaceVariables($template->content, $sale);

            $this->dispatchSystemSms(
                $company,
                $sale->customer,
                $message,
                $sale->branch_id,
                $template->id,
                $isTestMode
            );

            // Track when the initial invoice creation SMS is sent
            if ($eventType === 'invoice_created') {
                $sale->update(['invoice_created_sms_sent_at' => now()]);
            }

        } catch (\Exception $e) {
            Log::error('SystemSmsService Sale Error: '.$e->getMessage());
        }
    }

    public function sendReturnNotification(ReturnInward $return)
    {
        try {
            $return->loadMissing(['sale', 'branch.company', 'customer.area']);

            // Skip SMS for returns on Pay & SelfCollect (Pay Now) sales
            if ($return->sale && $return->sale->delivery_method_id == 1) {
                Log::info("SystemSmsService: Skipped Return SMS for Pay & SelfCollect sale #{$return->sale_id}");

                return;
            }

            $company = $return->branch?->company;

            if (! $company || ! $company->system_sms_enabled) {
                return;
            }

            $isTestMode = (bool) $company->system_sms_test_mode && ! empty($company->system_sms_test_phone);

            if ($return->customer && $return->customer->debtor_number === 'DBT-0001') {
                return;
            }

            if ($return->customer && $return->customer->area && $return->customer->area->is_retail) {
                return;
            }

            $template = SystemSmsTemplate::where('company_id', $company->id)
                ->where('event_type', 'returns')
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return;
            }

            $config = SmsConfig::where('company_id', $company->id)
                ->where('is_active', true)
                ->where(function ($q) use ($return) {
                    if ($return->branch_id) {
                        $q->where('branch_id', $return->branch_id)
                            ->orWhereNull('branch_id');
                    }
                })
                ->orderByRaw('branch_id IS NOT NULL DESC')
                ->first();

            if (! $config) {
                Log::warning("SystemSmsService: No active SMS config found for company {$company->id}");

                return;
            }

            $message = $this->replaceReturnVariables($template->content, $return);

            $this->dispatchSystemSms(
                $company,
                $return->customer,
                $message,
                $return->branch_id,
                $template->id,
                $isTestMode
            );

        } catch (\Exception $e) {
            Log::error('SystemSmsService Return Error: '.$e->getMessage());
        }
    }

    public function sendPaymentNotification($customer, float $amount, string $reference, $branchId = null, string $eventType = 'invoice_payment', $companyId = null, ?Sale $sale = null, ?int $outletId = null)
    {
        try {
            if (! $customer) {
                return;
            }

            // Suppress payment SMS ONLY if the invoice is specifically for Pay & Self Collect (delivery_method_id == 1)
            if ($sale && (int) $sale->delivery_method_id === 1) {
                Log::info("SystemSmsService: Skipped Payment SMS for Pay & Self Collect Sale #{$sale->id}");

                return;
            }

            $companyId = $companyId ?? $customer->company_id ?? Auth::user()?->company_id;
            $company = Company::find($companyId);

            if (! $company || ! $company->system_sms_enabled) {
                return;
            }

            $isTestMode = (bool) $company->system_sms_test_mode && ! empty($company->system_sms_test_phone);

            if ($customer->debtor_number === 'DBT-0001') {
                return;
            }

            if ($customer instanceof Customer) {
                $customer->loadMissing('area');
                if ($customer->area && $customer->area->is_retail) {
                    Log::info('Skipped SystemSmsService Payment Notification for retail customer', [
                        'customer_id' => $customer->id,
                        'reference' => $reference,
                    ]);

                    return;
                }
            }

            $template = SystemSmsTemplate::where('company_id', $companyId)
                ->where('event_type', $eventType)
                ->where('is_active', true)
                ->first();

            // Fallback for cheque_cleared to invoice_payment if not configured
            if (! $template && $eventType === 'cheque_cleared') {
                $template = SystemSmsTemplate::where('company_id', $companyId)
                    ->where('event_type', 'invoice_payment')
                    ->where('is_active', true)
                    ->first();
            }

            if (! $template) {
                return;
            }

            $config = SmsConfig::where('company_id', $companyId)
                ->where('is_active', true)
                ->where(function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId)
                            ->orWhereNull('branch_id');
                    }
                })
                ->orderByRaw('branch_id IS NOT NULL DESC')
                ->first();

            if (! $config) {
                Log::warning("SystemSmsService: No active SMS config found for company {$companyId}");

                return;
            }

            $message = $this->replacePaymentVariables($template->content, $customer, $amount, $reference, $branchId, $sale, $outletId);

            $this->dispatchSystemSms(
                $company,
                $customer,
                $message,
                $branchId,
                $template->id,
                $isTestMode
            );

        } catch (\Exception $e) {
            Log::error('SystemSmsService Payment Error: '.$e->getMessage());
        }
    }

    public function sendChequeReceivedNotification($customer, float $amount, string $chequeNumber, ?string $bankName = null, $branchId = null, $companyId = null, ?int $outletId = null)
    {
        try {
            if (! $customer) {
                return;
            }

            $companyId = $companyId ?? $customer->company_id ?? Auth::user()?->company_id;
            $company = Company::find($companyId);

            if (! $company || ! $company->system_sms_enabled) {
                return;
            }

            $isTestMode = (bool) $company->system_sms_test_mode && ! empty($company->system_sms_test_phone);

            if ($customer->debtor_number === 'DBT-0001') {
                return;
            }

            if ($customer instanceof Customer) {
                $customer->loadMissing('area');
                if ($customer->area && $customer->area->is_retail) {
                    Log::info('Skipped SystemSmsService Cheque Received Notification for retail customer', [
                        'customer_id' => $customer->id,
                        'cheque_number' => $chequeNumber,
                    ]);

                    return;
                }
            }

            $template = SystemSmsTemplate::where('company_id', $companyId)
                ->where('event_type', 'cheque_received')
                ->where('is_active', true)
                ->first();

            if (! $template) {
                Log::info("SystemSmsService: No active SMS template for cheque_received on company {$companyId}");

                return;
            }

            $config = SmsConfig::where('company_id', $companyId)
                ->where('is_active', true)
                ->where(function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId)
                            ->orWhereNull('branch_id');
                    }
                })
                ->orderByRaw('branch_id IS NOT NULL DESC')
                ->first();

            if (! $config) {
                Log::warning("SystemSmsService: No active SMS config found for company {$companyId}");

                return;
            }

            $message = $this->replaceChequeReceivedVariables($template->content, $customer, $amount, $chequeNumber, $bankName, $branchId, $company, $outletId);

            $this->dispatchSystemSms(
                $company,
                $customer,
                $message,
                $branchId,
                $template->id,
                $isTestMode
            );

        } catch (\Exception $e) {
            Log::error('SystemSmsService Cheque Received Error: '.$e->getMessage());
        }
    }

    /**
     * Dispatch SMS to recipient(s) handling pure test intercept, real customer delivery, and shadow duplicate copies.
     */
    protected function dispatchSystemSms($company, $customer, string $message, ?int $branchId, ?int $templateId, bool $isTestMode): void
    {
        $customerPhone = $customer ? ($customer->telephone ?? '') : '';
        $devPhone = ! empty($company->system_sms_test_phone) ? $company->system_sms_test_phone : ($company->system_sms_copy_phone ?? null);

        // 1. Sandbox Test Intercept Mode (Intercepts and sends ONLY to developer phone)
        if ($isTestMode) {
            if (! empty($devPhone)) {
                SendSingleSms::dispatchAfterResponse(
                    $devPhone,
                    $message,
                    $company->id,
                    $branchId,
                    null,
                    $templateId
                );
            }
        } else {
            // 2. Real Customer Delivery
            if (! empty($customerPhone)) {
                SendSingleSms::dispatchAfterResponse(
                    $customerPhone,
                    $message,
                    $company->id,
                    $branchId,
                    null,
                    $templateId
                );
            }

            // 3. Duplicate / Shadow Copy Delivery (Sends copy to developer phone while in production)
            if (! empty($company->system_sms_copy_enabled) && ! empty($devPhone)) {
                $copyMessage = "[{$message}]";
                SendSingleSms::dispatchAfterResponse(
                    $devPhone,
                    $copyMessage,
                    $company->id,
                    $branchId,
                    null,
                    $templateId
                );
            }
        }
    }

    /**
     * Strictly resolve Paybill Number for the specific outlet.
     * Zero fallbacks: Never falls back to company, branch, or unassigned global Paybills.
     */
    protected function resolvePaybillNumber(?int $outletId = null): string
    {
        if (! $outletId) {
            return '';
        }

        $shortcode = MpesaConfig::where('is_active', true)
            ->where('outlet_id', $outletId)
            ->value('shortcode');

        return ! empty($shortcode) ? (string) $shortcode : '';
    }

    /**
     * Resolve Customer Account strictly to customer's registered mpesa_name.
     * Returns empty string if not registered (no placeholder fallback).
     */
    protected function resolveCustomerAccount($customer): string
    {
        if (! $customer) {
            return '';
        }

        return ! empty($customer->mpesa_name) ? (string) $customer->mpesa_name : '';
    }

    protected function replaceVariables(string $content, Sale $sale): string
    {
        $company = $sale->branch?->company;
        $branch = $sale->branch;
        $customer = $sale->customer;

        $balanceService = app(CustomerBalanceService::class);
        $settlement = $balanceService->getSaleSettlement($sale);
        $outstandingBalance = $customer ? $balanceService->getCustomerNetBalance($customer) : 0.0;

        // If invoice has an outstanding balance, show the remaining invoice balance; otherwise show total amount
        $invoiceBalance = $settlement['balance'];
        $displayAmount = $invoiceBalance > 0.01 ? $invoiceBalance : $settlement['total'];

        $createdDate = $sale->created_at ? Carbon::parse($sale->created_at) : now();
        $dueDateObj = $sale->due_date
            ? Carbon::parse($sale->due_date)
            : $createdDate->copy()->addDays((int) ($customer?->credit_payment_terms ?? 0));

        $overdueDays = 0;
        if (now()->greaterThan($dueDateObj)) {
            $overdueDays = (int) $dueDateObj->diffInDays(now());
            if ($overdueDays === 0) {
                $overdueDays = 1;
            }
        }

        $paybill = $this->resolvePaybillNumber($sale->outlet_id);
        $account = $this->resolveCustomerAccount($customer);

        $replacements = [
            '{date}' => $createdDate->format('d/m/Y'),
            '{amount}' => number_format($displayAmount, 2),
            '{invoice_amount}' => number_format($settlement['total'], 2),
            '{invoice_total}' => number_format($settlement['total'], 2),
            '{invoice_balance}' => number_format($invoiceBalance, 2),
            '{total_balance}' => number_format($outstandingBalance, 2),
            '{outstanding_balance}' => number_format($outstandingBalance, 2),
            '{balance}' => number_format($outstandingBalance, 2),
            '{due_date}' => $dueDateObj->format('d/m/Y H:i'),
            '{overdue_days}' => (string) $overdueDays,
            '{paybill_number}' => $paybill,
            '{paybill}' => $paybill,
            '{account_number}' => $account,
            '{account}' => $account,
            '{company_name}' => $company?->name ?? '',
            '{customer_name}' => $customer->contact_person_names ?? 'Valued Customer',
            '{customer_company_name}' => $customer->company_name ?? $customer->contact_person_names ?? 'Valued Customer',
            '{invoice_number}' => $sale->invoice_number,
            '{payment_method}' => $sale->payment_method,
            '{branch_phone}' => $branch?->contact ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function replaceReturnVariables(string $content, ReturnInward $return): string
    {
        $company = $return->branch?->company;
        $branch = $return->branch;
        $customer = $return->customer;

        $balanceService = app(CustomerBalanceService::class);
        $outstandingBalance = $customer ? $balanceService->getCustomerNetBalance($customer) : 0.0;

        $returnDate = $return->created_at ? Carbon::parse($return->created_at) : now();
        $outletId = $return->sale?->outlet_id ?? $return->outlet_id ?? null;
        $paybill = $this->resolvePaybillNumber($outletId);
        $account = $this->resolveCustomerAccount($customer);

        $replacements = [
            '{date}' => $returnDate->format('d/m/Y'),
            '{amount}' => number_format($return->total_amount, 2),
            '{total_balance}' => number_format($outstandingBalance, 2),
            '{outstanding_balance}' => number_format($outstandingBalance, 2),
            '{balance}' => number_format($outstandingBalance, 2),
            '{paybill_number}' => $paybill,
            '{paybill}' => $paybill,
            '{account_number}' => $account,
            '{account}' => $account,
            '{company_name}' => $company?->name ?? '',
            '{customer_name}' => $customer->contact_person_names ?? 'Valued Customer',
            '{customer_company_name}' => $customer->company_name ?? $customer->contact_person_names ?? 'Valued Customer',
            '{credit_note_number}' => $return->return_number,
            '{branch_phone}' => $branch?->contact ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function replacePaymentVariables(string $content, $customer, float $amount, string $reference, $branchId = null, ?Sale $sale = null, ?int $outletId = null): string
    {
        $company = ! empty($customer?->company_id) ? Company::find($customer->company_id) : null;

        $balanceService = app(CustomerBalanceService::class);
        $outstandingBalance = $customer ? $balanceService->getCustomerNetBalance($customer) : 0.0;

        $days = (int) ($customer?->credit_payment_terms ?? 0);
        $dueDate = now()->addDays($days)->format('d/m/Y H:i');

        $effectiveOutletId = $outletId ?? $sale?->outlet_id ?? Auth::user()?->outlet_id ?? null;
        $effectiveBranchId = $sale?->branch_id ?? $branchId ?? Auth::user()?->branch_id ?? null;
        $paybill = $this->resolvePaybillNumber($effectiveOutletId);
        $account = $this->resolveCustomerAccount($customer);

        $replacements = [
            '{date}' => now()->format('d/m/Y H:i'),
            '{due_date}' => $dueDate,
            '{amount}' => number_format($amount, 2),
            '{total_balance}' => number_format($outstandingBalance, 2),
            '{outstanding_balance}' => number_format($outstandingBalance, 2),
            '{balance}' => number_format($outstandingBalance, 2),
            '{paybill_number}' => $paybill,
            '{paybill}' => $paybill,
            '{account_number}' => $account,
            '{account}' => $account,
            '{company_name}' => $company?->name ?? '',
            '{customer_name}' => $customer->contact_person_names ?? 'Valued Customer',
            '{customer_company_name}' => $customer->company_name ?? $customer->contact_person_names ?? 'Valued Customer',
            '{payment_reference}' => $reference,
            '{cheque_number}' => $reference,
            '{bank_name}' => '',
            '{mpesa_receipt}' => $reference,
            '{mpesa_phone}' => $customer->telephone ?? '',
            '{branch_phone}' => $sale?->branch?->contact ?? ($effectiveBranchId ? Branch::find($effectiveBranchId)?->contact : ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function replaceChequeReceivedVariables(string $content, $customer, float $amount, string $chequeNumber, ?string $bankName = null, $branchId = null, $company = null, ?int $outletId = null): string
    {
        $company = $company ?? (! empty($customer?->company_id) ? Company::find($customer->company_id) : null);
        $effectiveOutletId = $outletId ?? Auth::user()?->outlet_id ?? null;
        $paybill = $this->resolvePaybillNumber($effectiveOutletId);
        $account = $this->resolveCustomerAccount($customer);

        $replacements = [
            '{date}' => now()->format('d/m/Y H:i'),
            '{amount}' => number_format($amount, 2),
            '{cheque_number}' => $chequeNumber,
            '{bank_name}' => $bankName ?? '',
            '{payment_reference}' => $chequeNumber,
            '{paybill_number}' => $paybill,
            '{paybill}' => $paybill,
            '{account_number}' => $account,
            '{account}' => $account,
            '{company_name}' => $company?->name ?? '',
            '{customer_name}' => $customer->contact_person_names ?? 'Valued Customer',
            '{customer_company_name}' => $customer->company_name ?? $customer->contact_person_names ?? 'Valued Customer',
            '{branch_phone}' => $branchId ? (Branch::find($branchId)?->contact ?? '') : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Send SMS notification for M-Pesa Payment received.
     */
    public function sendMpesaPaymentNotification($transaction): void
    {
        try {
            $phone = $transaction->msisdn ?: $transaction->phone_number;
            if (! $phone) {
                return;
            }

            $message = 'Confirmed. Received KES '.number_format((float) $transaction->amount, 2)
                .' from '.($transaction->first_name ?: $phone)
                .' Ref: '.($transaction->trans_id ?: $transaction->bill_ref_number)
                .' on '.now()->format('d/m/Y H:i');

            $config = SmsConfig::where('is_active', true)->first();
            if ($config) {
                $this->smsService->send($config, $phone, $message);
            }
        } catch (\Exception $e) {
            Log::warning('SystemSmsService Mpesa notification error: '.$e->getMessage());
        }
    }

    /**
     * Send SMS notification for Customer Wallet Credit.
     */
    public function sendWalletCreditNotification($transaction): void
    {
        try {
            $customer = $transaction->customer;
            $phone = $customer ? ($customer->telephone ?? null) : null;
            if (! $phone) {
                return;
            }

            $message = 'Your wallet has been credited with KES '.number_format((float) $transaction->amount, 2)
                .'. New balance: KES '.number_format((float) $transaction->balance_after, 2)
                .' Ref: '.$transaction->reference_number;

            $config = SmsConfig::where('is_active', true)->first();
            if ($config) {
                $this->smsService->send($config, $phone, $message);
            }
        } catch (\Exception $e) {
            Log::warning('SystemSmsService Wallet notification error: '.$e->getMessage());
        }
    }

    /**
     * General variable substitution utility.
     */
    public function substituteVariables(string $template, array $data): string
    {
        $placeholders = [];
        foreach ($data as $key => $val) {
            $placeholders['{'.$key.'}'] = (string) $val;
        }

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
}

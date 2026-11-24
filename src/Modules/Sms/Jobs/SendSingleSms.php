<?php

namespace Ongudidan\FortuneCore\Modules\Sms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsConfig;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsLog;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;

class SendSingleSms implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $phoneNumber,
        protected string $message,
        protected int $companyId,
        protected ?int $branchId = null,
        protected ?int $templateId = null,
        protected ?int $systemSmsTemplateId = null,
    ) {}

    public function handle(SmsService $smsService): void
    {
        // Find the active config for this company/branch (branch-specific or company-wide)
        $config = SmsConfig::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->where(function ($q) {
                if ($this->branchId) {
                    $q->where('branch_id', $this->branchId)
                        ->orWhereNull('branch_id');
                }
            })
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if (! $config) {
            Log::error("SendSingleSms: No active SMS config found for company {$this->companyId}");

            return;
        }

        try {
            $response = $smsService->sendRaw(
                $config->partner_id,
                $config->api_key,
                $config->sender_id,
                $this->phoneNumber,
                $this->message
            );

            $resData = $response['responses'][0] ?? null;
            $networkName = isset($resData['networkid']) ? SmsLog::mapNetworkId($resData['networkid']) : null;

            SmsLog::create([
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'sms_config_id' => $config->id,
                'sms_template_id' => $this->templateId,
                'system_sms_template_id' => $this->systemSmsTemplateId,
                'phone_number' => $this->phoneNumber,
                'network' => $networkName,
                'message' => $this->message,
                'message_id' => $resData['messageid'] ?? null,
                'status' => ($resData['response-code'] ?? null) == 200 ? 'sent' : 'failed',
                'delivery_status' => 'pending',
                'provider_response' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error("SendSingleSms Job Error for {$this->phoneNumber}: ".$e->getMessage());

            SmsLog::create([
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'sms_config_id' => $config->id,
                'sms_template_id' => $this->templateId,
                'system_sms_template_id' => $this->systemSmsTemplateId,
                'phone_number' => $this->phoneNumber,
                'message' => $this->message,
                'status' => 'failed',
                'delivery_status' => 'failed',
            ]);
        }
    }
}

<?php

namespace Ongudidan\FortuneCore\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsCampaign;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsConfig;
use Ongudidan\FortuneCore\Modules\Sms\Models\SmsTemplate;
use Ongudidan\FortuneCore\Modules\Sms\Services\SmsService;

class SmsController extends Controller
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Send a single SMS.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1000',
            'config_id' => 'nullable|integer',
            'template_id' => 'nullable|integer',
        ]);

        $config = $request->filled('config_id')
            ? SmsConfig::find($request->config_id)
            : SmsConfig::where('is_active', true)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'No active SMS configuration found.',
            ], 422);
        }

        try {
            $log = $this->smsService->send(
                $config,
                $validated['phone'],
                $validated['message'],
                $validated['template_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'SMS dispatched successfully.',
                'log' => $log,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check SMS Balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $config = $request->filled('config_id')
            ? SmsConfig::find($request->config_id)
            : SmsConfig::where('is_active', true)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'No active SMS configuration found.',
            ], 422);
        }

        $balance = $this->smsService->getBalance($config);

        return response()->json([
            'success' => true,
            'balance' => $balance,
            'config_id' => $config->id,
            'sender_id' => $config->sender_id,
        ]);
    }

    /**
     * List SMS Templates.
     */
    public function templates(): JsonResponse
    {
        $templates = SmsTemplate::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * List SMS Campaigns.
     */
    public function campaigns(): JsonResponse
    {
        $campaigns = SmsCampaign::latest()->paginate(25);

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
        ]);
    }
}

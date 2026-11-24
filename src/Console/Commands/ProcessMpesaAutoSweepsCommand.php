<?php

namespace Ongudidan\FortuneCore\Console\Commands;

use Illuminate\Console\Command;
use Ongudidan\FortuneCore\Modules\Mpesa\Services\MpesaAutoSweepService;

class ProcessMpesaAutoSweepsCommand extends Command
{
    protected $signature = 'fortune:mpesa-auto-sweep';

    protected $description = 'Execute scheduled automated sweeps from M-Pesa Till/Paybill accounts to linked bank accounts';

    public function handle(MpesaAutoSweepService $autoSweepService): int
    {
        $this->info('Initiating automated M-Pesa account sweeps...');

        $results = $autoSweepService->executeSweeps();

        $this->info('Auto-sweep execution finished.');
        foreach ($results as $accountId => $res) {
            $status = $res['status'] ?? 'unknown';
            $msg = $res['reason'] ?? ($res['error'] ?? 'OK');
            $this->line("Account #{$accountId}: [{$status}] {$msg}");
        }

        return Command::SUCCESS;
    }
}

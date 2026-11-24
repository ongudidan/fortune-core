<?php

namespace Ongudidan\FortuneCore\Modules\Reports\Services;

use Ongudidan\FortuneCore\Modules\Payments\Models\PaymentAccount;
use Ongudidan\FortuneCore\Modules\Payments\Services\PaymentAccountService;

class VaultAuditReportService
{
    public function __construct(protected PaymentAccountService $accountService) {}

    /**
     * Vault Physical Audit: compares actual physical denomination counts against system book balance.
     */
    public function auditVault(PaymentAccount $account): array
    {
        return $this->accountService->healDenominationBalance($account);
    }

    /**
     * Audit all active vault accounts.
     */
    public function auditAllVaults(): array
    {
        $vaults = PaymentAccount::where('is_vault', true)->orWhere('has_denominations', true)->get();
        $results = [];

        foreach ($vaults as $vault) {
            $results[] = $this->auditVault($vault);
        }

        return $results;
    }
}

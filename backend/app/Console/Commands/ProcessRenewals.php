<?php

namespace App\Console\Commands;

use App\Modules\Billing\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * RC-5J — renouvellement & relance des abonnements (quotidien, cf. routes/console.php).
 */
class ProcessRenewals extends Command
{
    protected $signature   = 'billing:process-renewals';
    protected $description = 'Rappels d\'échéance, passage en past_due à expiration, suspension après grâce (RC-5J)';

    public function handle(RenewalService $renewals): int
    {
        $summary = $renewals->processRenewals();

        $this->info(sprintf(
            'Renouvellements — rappels: %d, past_due: %d, gratuits roulés: %d, suspendus: %d',
            $summary['reminders'],
            $summary['past_due'],
            $summary['rolled'],
            $summary['suspended'],
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\ImputationEngine;
use Illuminate\Console\Command;

/**
 * RC-26 — rejoue l'outbox du moteur d'imputation (événements métier → écritures comptables).
 * Idempotent (unicité tenant+event+source) : sans danger à programmer fréquemment.
 */
class ProcessAccountingOutbox extends Command
{
    protected $signature   = 'accounting:process-outbox {--limit=100}';
    protected $description = 'Génère les écritures comptables en attente depuis l\'outbox — RC-26';

    public function handle(ImputationEngine $engine): int
    {
        $result = $engine->processPending((int) $this->option('limit'));

        $this->info("Comptabilité — écritures générées: {$result['processed']}, échecs: {$result['failed']}");

        return self::SUCCESS;
    }
}

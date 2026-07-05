<?php

namespace App\Modules\ImportExport\Jobs;

use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\ImportExport\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Horizon job: execute a large approved import.
 * Dispatched when the number of valid rows exceeds the inline threshold.
 */
class ExecuteImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;    // Import must not retry partially; re-run manually if needed
    public int $timeout = 600;  // 10 minutes

    public function __construct(public readonly string $sessionId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImportService $service): void
    {
        $session = ImportSession::findOrFail($this->sessionId);

        if ($session->isCancelled() || $session->isTerminal()) {
            return;
        }

        $service->runExecution($session);
    }

    public function failed(\Throwable $e): void
    {
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            $session->update([
                'status'        => ImportSession::STATUS_FAILED,
                'error_message' => 'Exécution échouée: ' . $e->getMessage(),
            ]);
        }
    }
}

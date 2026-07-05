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
 * Horizon job: analyze a large import file.
 * Dispatched when row count exceeds the inline threshold.
 */
class AnalyzeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(public readonly string $sessionId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImportService $service): void
    {
        $session = ImportSession::findOrFail($this->sessionId);

        // Guard: session may have been cancelled while job was queued
        if ($session->isCancelled() || $session->isTerminal()) {
            return;
        }

        $service->runAnalysis($session);
    }

    public function failed(\Throwable $e): void
    {
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            $session->update([
                'status'        => ImportSession::STATUS_FAILED,
                'error_message' => 'Analyse échouée: ' . $e->getMessage(),
            ]);
        }
    }
}

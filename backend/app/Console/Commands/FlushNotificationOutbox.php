<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * RC-6A — expédie l'outbox de notifications (cf. Schedule toutes les 5 minutes).
 */
class FlushNotificationOutbox extends Command
{
    protected $signature   = 'notifications:flush-outbox {--limit=50}';
    protected $description = 'Expédie les notifications en attente (retry borné) — RC-6A';

    public function handle(NotificationService $notifications): int
    {
        $result = $notifications->flush((int) $this->option('limit'));

        $this->info("Notifications — envoyées: {$result['sent']}, échecs: {$result['failed']}");

        return self::SUCCESS;
    }
}

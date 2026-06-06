<?php

namespace App\Console\Commands;

use App\Modules\Auth\Services\TemporaryAccessService;
use Illuminate\Console\Command;

/**
 * Revokes lapsed temporary access grants — runs on the scheduler so temporary
 * access expires WITHOUT any manual intervention.
 */
class RevokeExpiredAccess extends Command
{
    protected $signature = 'access:revoke-expired';

    protected $description = 'Revoke temporary access grants whose expiry has passed';

    public function handle(TemporaryAccessService $service): int
    {
        $count = $service->revokeExpired();
        $this->info("Temporary access grants revoked: {$count}");

        return self::SUCCESS;
    }
}

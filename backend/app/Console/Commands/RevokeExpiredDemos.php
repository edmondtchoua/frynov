<?php

namespace App\Console\Commands;

use App\Modules\Demo\Mail\DemoEndedMail;
use App\Modules\Demo\Models\DemoRequest;
use App\Modules\Demo\Services\DemoProvisioningService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Démonte les accès démo expirés (tenant éphémère détruit, comptes révoqués).
 * Tourne sur le scheduler → expiration sans intervention manuelle.
 */
class RevokeExpiredDemos extends Command
{
    protected $signature = 'demo:revoke-expired';

    protected $description = 'Revoke and tear down demo accesses whose expiry has passed';

    public function handle(DemoProvisioningService $provisioning, AuditService $audit): int
    {
        $expired = DemoRequest::query()->dueForExpiry()->get();

        foreach ($expired as $request) {
            $email = $request->email;
            $provisioning->revoke($request);
            $audit->log('demo.expired_auto', null, null, $request, notes: $email);

            try {
                Mail::to($email)->send(new DemoEndedMail($request));
            } catch (\Throwable $e) {
                $this->warn("Email de fin de démo non envoyé à {$email}: {$e->getMessage()}");
            }
        }

        $this->info("Demo accesses revoked: {$expired->count()}");

        return self::SUCCESS;
    }
}

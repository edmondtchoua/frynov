<?php

namespace App\Console\Commands;

use App\Modules\Demo\Mail\DemoReminderMail;
use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un rappel aux prospects dont l'accès démo expire dans N jours
 * (config demo.reminder_days_before), une seule fois par demande.
 */
class SendDemoReminders extends Command
{
    protected $signature = 'demo:send-reminders';

    protected $description = 'Send a reminder before demo accesses expire';

    public function handle(): int
    {
        $daysBefore = (int) config('demo.reminder_days_before', 3);
        if ($daysBefore <= 0) {
            $this->info('Rappels désactivés (reminder_days_before <= 0).');
            return self::SUCCESS;
        }

        $threshold = now()->addDays($daysBefore);

        $due = DemoRequest::query()
            ->where('status', DemoRequest::STATUS_ACCESS_SENT)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('demo_access_expires_at')
            ->where('demo_access_expires_at', '>', now())
            ->where('demo_access_expires_at', '<=', $threshold)
            ->get();

        $sent = 0;
        foreach ($due as $request) {
            try {
                Mail::to($request->email)->send(
                    new DemoReminderMail($request, $request->demo_access_expires_at->format('d/m/Y'))
                );
                $request->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Rappel non envoyé à {$request->email}: {$e->getMessage()}");
            }
        }

        $this->info("Rappels de démo envoyés: {$sent}");

        return self::SUCCESS;
    }
}

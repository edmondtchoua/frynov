<?php

use App\Modules\Inventory\Jobs\InventorySnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Axe 3 — Daily inventory snapshots ──────────────────────────────────────
// Runs at 00:15 every night (after midnight transactions have settled).
// Generates materialized view of closing stock per (tenant, warehouse, SKU, day).
// The job implements ShouldQueue so it runs async on the 'snapshots' queue worker.
Schedule::job(new InventorySnapshotJob())
    ->dailyAt('00:15')
    ->name('inventory:snapshot-daily')
    ->withoutOverlapping(30);  // 30 min overlap guard

// ── RBAC Phase C — auto-expire temporary access grants ─────────────────────
// Revokes lapsed temporary roles every minute → access expires without any
// manual action (the ≤1-min window is acceptable; tighten the cadence if needed).
Schedule::command('access:revoke-expired')
    ->everyMinute()
    ->name('access:revoke-expired')
    ->withoutOverlapping();

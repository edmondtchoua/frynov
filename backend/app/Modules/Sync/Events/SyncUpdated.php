<?php

namespace App\Modules\Sync\Events;

use App\Modules\Sync\Models\Sync;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Sync $model,
    ) {}
}
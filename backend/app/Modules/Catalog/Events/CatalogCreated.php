<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\Catalog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CatalogCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Catalog $model,
    ) {}
}
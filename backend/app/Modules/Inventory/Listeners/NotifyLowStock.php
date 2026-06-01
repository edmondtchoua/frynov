<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Events\LowStockDetected;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Handles LowStockDetected events.
 *
 * Currently: logs to audit_logs for traceability.
 * Future Phase 2: extend to send in-app notifications / email to purchasing manager.
 *
 * Implements ShouldQueue to avoid blocking the HTTP response — processes asynchronously.
 */
class NotifyLowStock implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly AuditService $audit) {}

    public function handle(LowStockDetected $event): void
    {
        $stock   = $event->stock;
        $product = $stock->product;

        $this->audit->log(
            action:      'inventory.low_stock_alert',
            subjectType: \App\Modules\Inventory\Models\Stock::class,
            subjectId:   $stock->id,
            newValues:   [
                'product_id'        => $stock->product_id,
                'sku'               => $product?->sku,
                'product_name'      => $product?->name,
                'available'         => $stock->available(),
                'quantity'          => $stock->quantity,
                'reserved_quantity' => $stock->reserved_quantity,
                'threshold'         => $stock->low_stock_threshold,
            ],
            tenantId: $stock->tenant_id,
        );
    }

    public function failed(LowStockDetected $event, \Throwable $exception): void
    {
        \Log::error('NotifyLowStock listener failed', [
            'stock_id' => $event->stock->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}

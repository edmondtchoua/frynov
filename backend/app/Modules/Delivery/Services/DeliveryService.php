<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DeliveryService
{
    // ── Queries ────────────────────────────────────────────────────────────────

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $q = Delivery::forTenant($tenantId)->with('order');

        if (!empty($filters['status']))   $q->where('status', $filters['status']);
        if (!empty($filters['order_id'])) $q->where('order_id', $filters['order_id']);

        return $q->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findOrFail(string $id, string $tenantId): Delivery
    {
        return Delivery::forTenant($tenantId)
            ->with('order')
            ->findOrFail($id);
    }

    public function listForOrder(string $orderId, string $tenantId): Collection
    {
        return Delivery::forTenant($tenantId)
            ->where('order_id', $orderId)
            ->latest()
            ->get();
    }

    // ── Commands ───────────────────────────────────────────────────────────────

    public function create(array $data, string $tenantId, string $userId): Delivery
    {
        return Delivery::create([
            ...$data,
            'tenant_id'    => $tenantId,
            'performed_by' => $userId,
            'status'       => Delivery::STATUS_PENDING,
        ]);
    }

    public function dispatch(Delivery $delivery): Delivery
    {
        if (!$delivery->canBeDispatched()) {
            throw new \DomainException(
                "Cannot dispatch a delivery with status '{$delivery->status}'."
            );
        }

        $delivery->update([
            'status'        => Delivery::STATUS_DISPATCHED,
            'dispatched_at' => now(),
        ]);

        return $delivery->fresh('order');
    }

    public function confirm(Delivery $delivery): Delivery
    {
        if (!$delivery->canBeDelivered()) {
            throw new \DomainException(
                "Cannot confirm delivery with status '{$delivery->status}'."
            );
        }

        $delivery->update([
            'status'       => Delivery::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        return $delivery->fresh('order');
    }

    public function fail(Delivery $delivery, string $reason): Delivery
    {
        if (!$delivery->canBeFailed()) {
            throw new \DomainException('Cannot fail an already delivered delivery.');
        }

        $delivery->update([
            'status'        => Delivery::STATUS_FAILED,
            'failed_at'     => now(),
            'failed_reason' => $reason,
        ]);

        return $delivery->fresh('order');
    }
}

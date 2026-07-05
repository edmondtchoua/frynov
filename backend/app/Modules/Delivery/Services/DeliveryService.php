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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $tenantId, $userId) {
            // ── Atomic BL number generation (reuses sku_sequences) ────────────
            \Illuminate\Support\Facades\DB::table('sku_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'prefix'    => 'BL',
                'last_seq'  => 0,
            ]);

            $row = \Illuminate\Support\Facades\DB::table('sku_sequences')
                ->where('tenant_id', $tenantId)
                ->where('prefix', 'BL')
                ->lockForUpdate()
                ->first();

            $blNumber = 'BL-' . str_pad((string) ($row->last_seq + 1), 5, '0', STR_PAD_LEFT);

            \Illuminate\Support\Facades\DB::table('sku_sequences')
                ->where('tenant_id', $tenantId)
                ->where('prefix', 'BL')
                ->update(['last_seq' => $row->last_seq + 1]);

            return Delivery::create([
                ...(array)$data,
                'number'       => $blNumber,
                'tenant_id'    => $tenantId,
                'performed_by' => $userId,
                'status'       => Delivery::STATUS_PENDING,
            ]);
        });
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

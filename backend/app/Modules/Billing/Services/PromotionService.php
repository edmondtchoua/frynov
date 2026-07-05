<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\PromoUse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Validate a promo code for a given tenant and plan.
     * Throws InvalidPromoCodeException on any failure.
     */
    public function validate(string $code, Tenant $tenant, ?string $planCode = null): Promotion
    {
        $promo = Promotion::where('code', strtoupper(trim($code)))->first();

        if (! $promo || ! $promo->is_active) {
            throw new InvalidPromoCodeException('Code promotionnel invalide ou inactif.');
        }

        if ($promo->hasNotStarted()) {
            throw new InvalidPromoCodeException('Ce code n\'est pas encore valide.');
        }

        if ($promo->isExpired()) {
            throw new InvalidPromoCodeException('Ce code promotionnel a expiré.');
        }

        if ($promo->isUsageLimitReached()) {
            throw new InvalidPromoCodeException('La limite d\'utilisation de ce code a été atteinte.');
        }

        if ($planCode && ! $promo->appliesToPlan($planCode)) {
            throw new InvalidPromoCodeException('Ce code ne s\'applique pas à ce plan.');
        }

        // Check if this tenant already used this promo
        $alreadyUsed = PromoUse::where('promotion_id', $promo->id)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($alreadyUsed) {
            throw new InvalidPromoCodeException('Vous avez déjà utilisé ce code promotionnel.');
        }

        return $promo;
    }

    /**
     * Record usage and increment the counter (call after payment/plan activation).
     */
    public function recordUse(Promotion $promo, Tenant $tenant): PromoUse
    {
        return DB::transaction(function () use ($promo, $tenant) {
            $use = PromoUse::create([
                'promotion_id' => $promo->id,
                'tenant_id'    => $tenant->id,
                'used_at'      => now(),
            ]);

            $promo->increment('current_uses');

            return $use;
        });
    }

    /**
     * Get all promotions, paginated or full list.
     */
    public function list(int $perPage = 30)
    {
        return Promotion::latest()->paginate($perPage);
    }

    /**
     * Create a new promotion.
     */
    public function create(array $data): Promotion
    {
        return Promotion::create([
            'code'              => strtoupper(trim($data['code'])),
            'description'       => $data['description'] ?? null,
            'discount_type'     => $data['discount_type'],
            'discount_value'    => $data['discount_value'],
            'applicable_plans'  => $data['applicable_plans'] ?? null,
            'valid_from'        => $data['valid_from'] ?? null,
            'valid_until'       => $data['valid_until'] ?? null,
            'max_uses'          => $data['max_uses'] ?? null,
            'is_active'         => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing promotion.
     */
    public function update(Promotion $promo, array $data): Promotion
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $promo->update($data);

        return $promo->fresh();
    }
}

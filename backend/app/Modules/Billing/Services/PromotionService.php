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
     * P0.1 — Meilleure promotion « en cours » applicable AUTOMATIQUEMENT (sans code), pour un plan.
     *
     * Sélectionne parmi les promotions actives, valides maintenant, applicables au plan, non encore
     * utilisées par le tenant et sous leur limite d'usage — celle offrant la plus forte remise sur
     * `$referenceAmountMinor` (un tarif de période sert de référence). Renvoie null si aucune.
     */
    public function activeFor(Tenant $tenant, string $planCode, int $referenceAmountMinor): ?Promotion
    {
        $now = now();

        $candidates = Promotion::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now))
            ->get()
            ->filter(fn (Promotion $p) => $p->appliesToPlan($planCode)
                && ! $p->isUsageLimitReached()
                && ! PromoUse::where('promotion_id', $p->id)->where('tenant_id', $tenant->id)->exists());

        if ($candidates->isEmpty()) {
            return null;
        }

        // Meilleure remise sur le montant de référence (une période).
        return $candidates
            ->sortByDesc(fn (Promotion $p) => $referenceAmountMinor - $p->applyDiscount($referenceAmountMinor))
            ->first();
    }

    /**
     * Record usage and increment the counter (call after payment/plan activation).
     *
     * RC-20 (B-7) — la ligne promo est verrouillée FOR UPDATE et la limite re-vérifiée DANS la
     * transaction : deux activations concurrentes ne peuvent plus dépasser `max_uses` (le
     * `isUsageLimitReached()` de validate() lisait un compteur non verrouillé).
     *
     * @throws InvalidPromoCodeException si la limite est atteinte au moment de l'enregistrement.
     */
    public function recordUse(Promotion $promo, Tenant $tenant): PromoUse
    {
        return DB::transaction(function () use ($promo, $tenant) {
            $locked = Promotion::whereKey($promo->id)->lockForUpdate()->firstOrFail();

            if ($locked->isUsageLimitReached()) {
                throw new InvalidPromoCodeException('La limite d\'utilisation de ce code a été atteinte.');
            }

            $use = PromoUse::create([
                'promotion_id' => $locked->id,
                'tenant_id'    => $tenant->id,
                'used_at'      => now(),
            ]);

            $locked->increment('current_uses');

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

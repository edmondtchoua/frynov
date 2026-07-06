<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;

/**
 * P3 — aperçu (LECTURE SEULE) de l'impact d'un changement de plan AVANT confirmation.
 *
 * Objectif produit : ne JAMAIS supprimer de données métier à cause d'un changement de plan. On se
 * contente d'AVERTIR : modules qui seront retirés (downgrade) et quotas dépassés (utilisateurs /
 * produits / clients / entrepôts / commandes du mois en cours). L'application des restrictions est
 * ensuite assurée par l'existant (EnsureTenantHasModule + EnforceQuota) : au-delà des limites, la
 * CRÉATION est bloquée mais les données restent consultables.
 */
class DowngradeImpactService
{
    /** Ressources à quotas surveillées à l'aperçu. */
    private const RESOURCES = ['users', 'products', 'customers', 'warehouses', 'orders'];

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly QuotaService        $quotas,
    ) {}

    /**
     * @return array<string,mixed> {is_downgrade, from_plan, to_plan, modules_lost[], quota_overages[], has_impact}
     */
    public function forPlan(Tenant $tenant, Plan $targetPlan): array
    {
        $current     = $this->subscriptions->current($tenant);
        $currentPlan = $current?->plan;

        $isDowngrade = $currentPlan !== null
            && $currentPlan->id !== $targetPlan->id
            && (int) $targetPlan->sort_order < (int) $currentPlan->sort_order;

        // Modules retirés : présents dans le plan courant, absents du plan cible. (Aujourd'hui vide car
        // tous les modules sont sur tous les plans, mais la mécanique reste correcte pour l'avenir.)
        $modulesLost = [];
        if ($currentPlan) {
            $currentModules = $currentPlan->includedModules()->pluck('code')->all();
            $targetModules  = $targetPlan->includedModules()->pluck('code')->all();
            $modulesLost    = array_values(array_diff($currentModules, $targetModules));
        }

        // Quotas dépassés : usage courant > limite du plan cible.
        $overages = [];
        foreach (self::RESOURCES as $resource) {
            $limit = $this->quotas->planLimit($targetPlan, $resource);
            if (empty($limit)) {
                continue; // null/0 = illimité sur la cible → aucun impact
            }
            $usage = $this->quotas->usage($tenant, $resource);
            if ($usage > $limit) {
                $overages[] = [
                    'resource' => $resource,
                    'usage'    => $usage,
                    'limit'    => $limit,
                    'excess'   => $usage - $limit,
                ];
            }
        }

        return [
            'is_downgrade'   => $isDowngrade,
            'from_plan'      => $currentPlan?->code,
            'to_plan'        => $targetPlan->code,
            'modules_lost'   => $modulesLost,
            'quota_overages' => $overages,
            'has_impact'     => $modulesLost !== [] || $overages !== [],
        ];
    }
}

<?php

namespace App\Shared\Services;

use Illuminate\Support\Facades\DB;

/**
 * RC-20 (C-9/P-3) — séquences de numérotation SÉRIALISÉES par (tenant, préfixe).
 *
 * Remplace les générations `count()+1` (course : deux requêtes concurrentes lisaient le même
 * count → numéros dupliqués). Même mécanique éprouvée que `nextOrderNumber` (ORD-) : ligne
 * `sku_sequences` verrouillée `FOR UPDATE` le temps de l'incrément.
 *
 * DOIT être appelé dans une transaction (sinon le verrou est relâché immédiatement).
 */
class SequenceService
{
    /**
     * Prochain numéro `PREFIX-000123`.
     *
     * @param  \Closure|null  $seed  Compte initial pour un tenant existant AVANT la migration vers
     *                               les séquences (ex. `fn () => OrderReturn::count()`), afin de ne
     *                               pas redémarrer à 1 et dupliquer les numéros historiques.
     */
    public function next(string $tenantId, string $prefix, int $pad = 6, ?\Closure $seed = null): string
    {
        DB::table('sku_sequences')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'prefix'    => $prefix,
            'last_seq'  => 0,
        ]);

        $row = DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $current = (int) $row->last_seq;

        // Première utilisation pour ce préfixe : reprendre la continuité de l'historique count()+1.
        if ($current === 0 && $seed !== null) {
            $current = max(0, (int) $seed());
        }

        $next = $current + 1;

        DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', $prefix)
            ->update(['last_seq' => $next]);

        return $prefix . '-' . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }
}

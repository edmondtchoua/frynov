<?php

namespace App\Modules\Billing\Support;

/**
 * Référentiel marché ↔ devise ↔ exposant, partagé par la détection de périodicité (RC-1C) et,
 * à terme, par la sérialisation pricing. La devise n'est JAMAIS saisie librement côté tenant : le
 * marché est résolu serveur-side à partir de la devise du paiement (+ un hint optionnel cohérent).
 *
 * L'ordre de la table fixe la canonicité : pour une devise donnée, le PREMIER marché non `global`
 * gagne (XOF→waemu, XAF→cemac, USD→usa). `global` ne sert de canonique que pour une devise USD
 * sans marché national dédié — ici USD a déjà `usa`, donc `global` n'est jamais canonique.
 */
final class Markets
{
    /** code => [currency, exponent]. */
    private const MAP = [
        'waemu'        => ['XOF', 0],
        'cemac'        => ['XAF', 0],
        'nigeria'      => ['NGN', 2],
        'ghana'        => ['GHS', 2],
        'kenya'        => ['KES', 2],
        'south_africa' => ['ZAR', 2],
        'europe'       => ['EUR', 2],
        'canada'       => ['CAD', 2],
        'usa'          => ['USD', 2],
        'global'       => ['USD', 2],
    ];

    public static function isValid(string $code): bool
    {
        return isset(self::MAP[$code]);
    }

    public static function currencyFor(string $code): ?string
    {
        return self::MAP[$code][0] ?? null;
    }

    /** Exposant de la devise (XOF/XAF = 0 ; défaut 2). */
    public static function exponentForCurrency(string $currency): int
    {
        foreach (self::MAP as [$cur, $exp]) {
            if ($cur === $currency) {
                return $exp;
            }
        }

        return 2;
    }

    /**
     * Marché canonique d'une devise = premier marché non `global` la portant ; repli `global` si
     * seul `global` la porte ; null si la devise n'appartient à aucun marché (fail-safe « unmatched »).
     */
    public static function canonicalForCurrency(string $currency): ?string
    {
        foreach (self::MAP as $code => [$cur, $exp]) {
            if ($code !== 'global' && $cur === $currency) {
                return $code;
            }
        }

        return self::currencyFor('global') === $currency ? 'global' : null;
    }
}

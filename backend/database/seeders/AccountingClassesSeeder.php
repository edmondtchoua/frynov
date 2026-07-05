<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\AccountClass;
use Illuminate\Database\Seeder;

/**
 * RC-23 — classes SYSCOHADA révisé (référentiel GLOBAL, identique pour tous les tenants).
 */
class AccountingClassesSeeder extends Seeder
{
    private const CLASSES = [
        [1, 'Comptes de ressources durables',              AccountClass::TYPE_BALANCE],
        [2, 'Comptes d\'actif immobilisé',                 AccountClass::TYPE_BALANCE],
        [3, 'Comptes de stocks',                           AccountClass::TYPE_BALANCE],
        [4, 'Comptes de tiers',                            AccountClass::TYPE_BALANCE],
        [5, 'Comptes de trésorerie',                       AccountClass::TYPE_BALANCE],
        [6, 'Comptes de charges des activités ordinaires', AccountClass::TYPE_PNL],
        [7, 'Comptes de produits des activités ordinaires', AccountClass::TYPE_PNL],
        [8, 'Comptes des autres charges et produits (HAO)', AccountClass::TYPE_PNL],
        [9, 'Comptabilité analytique / engagements',       AccountClass::TYPE_PNL],
    ];

    public function run(): void
    {
        foreach (self::CLASSES as [$code, $name, $type]) {
            AccountClass::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }

        $this->command?->info('Classes SYSCOHADA seedées (1–9).');
    }
}

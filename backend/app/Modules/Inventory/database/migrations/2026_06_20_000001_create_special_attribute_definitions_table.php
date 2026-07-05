<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RC-6D (Phase 2D — attributs spéciaux dynamiques) — DÉFINITIONS d'identifiants métier configurables
 * sans code (audit produits-spéciaux §6.2). `tenant_id` null = définition GLOBALE seedée ; un tenant
 * peut créer les siennes. Le `serial_type` libre de RC-5B pointe désormais (logiquement) vers
 * `code` : validation (regex), normalisation (stratégie) et unicité sont pilotées par la définition.
 *
 * Seed EXHAUSTIF (arbitrage fondateur D) : téléphonie, véhicules, réseau, énergie/compteurs, médical,
 * certificats, lots… — tout ce qu'un commerce africain multi-secteurs identifie unité par unité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index(); // null = globale (seedée)

            $table->string('code', 40);              // imei, vin, mac_address…
            $table->string('label', 120);            // libellé d'affichage
            $table->string('scope', 24)->default('inventory_unit');
            // digits_only | alnum_upper | upper_trim | none
            $table->string('normalization_strategy', 24)->default('upper_trim');
            $table->string('validation_regex', 190)->nullable(); // appliquée à la valeur normalisée
            $table->boolean('is_unique')->default(true);          // unicité par tenant (RC-5B)
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->string('help_text', 190)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'special_attr_defs_scope_unique');
        });

        // ── Catalogue global exhaustif ──────────────────────────────────────
        $now  = now();
        $defs = [
            // Téléphonie / électronique
            ['code' => 'imei',              'label' => 'IMEI',                          'norm' => 'digits_only', 'regex' => '^\d{14,16}$',            'unique' => true,  'sort' => 10, 'help' => 'Identifiant téléphone GSM (15 chiffres).'],
            ['code' => 'imei2',             'label' => 'IMEI 2 (dual SIM)',             'norm' => 'digits_only', 'regex' => '^\d{14,16}$',            'unique' => true,  'sort' => 11, 'help' => 'Second IMEI des téléphones double SIM.'],
            ['code' => 'serial_number',     'label' => 'Numéro de série constructeur',  'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 20, 'help' => 'Numéro de série générique (électroménager, informatique…).'],
            ['code' => 'mac_address',       'label' => 'Adresse MAC',                   'norm' => 'alnum_upper', 'regex' => '^[0-9A-F]{12}$',          'unique' => true,  'sort' => 30, 'help' => 'Équipements réseau (12 caractères hexadécimaux, séparateurs ignorés).'],
            ['code' => 'iccid',             'label' => 'ICCID (carte SIM)',             'norm' => 'digits_only', 'regex' => '^\d{18,20}$',            'unique' => true,  'sort' => 31, 'help' => 'Numéro de carte SIM.'],
            ['code' => 'imsi',              'label' => 'IMSI',                          'norm' => 'digits_only', 'regex' => '^\d{14,15}$',            'unique' => true,  'sort' => 32, 'help' => 'Identifiant d\'abonné mobile.'],
            ['code' => 'msisdn',            'label' => 'Numéro de ligne (MSISDN)',      'norm' => 'digits_only', 'regex' => '^\d{8,15}$',             'unique' => true,  'sort' => 33, 'help' => 'Numéro d\'appel de la ligne.'],
            // Véhicules
            ['code' => 'vin',               'label' => 'VIN / numéro de châssis',       'norm' => 'alnum_upper', 'regex' => '^[A-HJ-NPR-Z0-9]{11,17}$', 'unique' => true, 'sort' => 40, 'help' => 'Véhicules (17 caractères, sans I, O, Q).'],
            ['code' => 'chassis_number',    'label' => 'Numéro de châssis (autre)',     'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 41, 'help' => 'Châssis non normalisé VIN (motos, tricycles…).'],
            ['code' => 'engine_number',     'label' => 'Numéro de moteur',              'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 42, 'help' => 'Gravé sur le bloc moteur.'],
            ['code' => 'plate_number',      'label' => 'Plaque d\'immatriculation',     'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 43, 'help' => 'Véhicules déjà immatriculés (occasion).'],
            // Énergie / compteurs / équipements
            ['code' => 'meter_number',      'label' => 'Numéro de compteur',            'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 50, 'help' => 'Compteurs eau / électricité prépayés.'],
            ['code' => 'battery_serial',    'label' => 'Numéro de batterie',            'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 51, 'help' => 'Batteries solaires / onduleurs.'],
            // Médical / conformité
            ['code' => 'medical_device_ref','label' => 'Référence équipement médical (UDI)', 'norm' => 'alnum_upper', 'regex' => null,                'unique' => true,  'sort' => 60, 'help' => 'Identifiant unique des dispositifs médicaux.'],
            ['code' => 'certificate_number','label' => 'Numéro de certificat',          'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 61, 'help' => 'Certificat d\'authenticité ou de conformité.'],
            ['code' => 'warranty_card_no',  'label' => 'Numéro de carte de garantie',   'norm' => 'alnum_upper', 'regex' => null,                      'unique' => true,  'sort' => 62, 'help' => 'Carte de garantie constructeur.'],
            // Divers
            ['code' => 'license_key',       'label' => 'Clé de licence (boîte)',        'norm' => 'upper_trim',  'regex' => null,                      'unique' => true,  'sort' => 70, 'help' => 'Logiciels vendus en boîte physique.'],
            ['code' => 'lot_number',        'label' => 'Numéro de lot fabricant',       'norm' => 'alnum_upper', 'regex' => null,                      'unique' => false, 'sort' => 80, 'help' => 'Lot fabricant — PARTAGÉ par plusieurs unités (non unique).'],
            ['code' => 'custom',            'label' => 'Identifiant personnalisé',      'norm' => 'upper_trim',  'regex' => null,                      'unique' => true,  'sort' => 99, 'help' => 'Tout autre identifiant métier.'],
        ];

        foreach ($defs as $d) {
            DB::table('special_attribute_definitions')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => null,
                'code' => $d['code'], 'label' => $d['label'], 'scope' => 'inventory_unit',
                'normalization_strategy' => $d['norm'], 'validation_regex' => $d['regex'],
                'is_unique' => $d['unique'], 'is_active' => true, 'sort_order' => $d['sort'],
                'help_text' => $d['help'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('special_attribute_definitions');
    }
};

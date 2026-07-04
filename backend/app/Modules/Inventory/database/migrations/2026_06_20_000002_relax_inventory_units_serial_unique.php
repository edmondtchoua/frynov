<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6D — l'unicité d'un identifiant devient PILOTÉE PAR SA DÉFINITION (`is_unique`) : un `lot_number`
 * est partagé par plusieurs unités, un IMEI reste unique. L'index DB strict de RC-5B (UNIQUE tenant/
 * type/valeur) est remplacé par un index simple ; la garantie d'unicité des types uniques reste
 * assurée par le contrôle applicatif transactionnel (`lockForUpdate`) de `InventoryUnitService`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropUnique('inv_units_tenant_serial_unique');
            $table->index(['tenant_id', 'serial_type', 'normalized_serial'], 'inv_units_tenant_serial_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropIndex('inv_units_tenant_serial_idx');
            $table->unique(['tenant_id', 'serial_type', 'normalized_serial'], 'inv_units_tenant_serial_unique');
        });
    }
};

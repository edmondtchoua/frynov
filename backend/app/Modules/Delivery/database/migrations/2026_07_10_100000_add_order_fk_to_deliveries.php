<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pose la FK deliveries.order_id -> orders.id APRES la creation de `orders`.
 *
 * La creation de `deliveries` (2026_05_30_154757) trie avant celle de `orders`
 * (2026_05_30_300001) : poser la FK en ligne cassait un `migrate` sur base
 * vierge (MySQL 8, erreur 1824). Migration idempotente : elle ne fait rien si
 * la contrainte existe deja (bases dev migrees incrementalement).
 */
return new class extends Migration
{
    private const FK_NAME = 'deliveries_order_id_foreign';

    public function up(): void
    {
        // FK ajoutée uniquement sous MySQL : SQLite (suite de tests) ne sait pas ALTER TABLE ADD
        // CONSTRAINT et n'a pas d'information_schema — la contrainte y serait de toute façon inutile.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('deliveries') || ! Schema::hasTable('orders')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (Schema::hasTable('deliveries') && $this->foreignKeyExists()) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropForeign(self::FK_NAME);
            });
        }
    }

    private function foreignKeyExists(): bool
    {
        return ! empty(DB::select(
            "SELECT 1
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'deliveries'
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [self::FK_NAME]
        ));
    }
};

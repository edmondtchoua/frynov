<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La désignation d'une ligne de facture est saisie en multi-lignes côté UI (textarea) : le
 * varchar(191) hérité de `defaultStringLength(191)` tronquait/refusait au-delà (SQL 1406 « Data too
 * long ») alors que la validation autorisait déjà 255. On passe en TEXT — libellés longs et
 * multi-lignes sans limite artificielle de colonne (la borne applicative reste la validation HTTP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->text('label')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('label', 191)->change();
        });
    }
};

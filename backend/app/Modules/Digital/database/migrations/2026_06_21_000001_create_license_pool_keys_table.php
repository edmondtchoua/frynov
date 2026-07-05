<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RC-6E (Phase 2E — pool de licences) — clés de licence ÉDITEUR pré-importées, consommées à la vente
 * (FIFO d'import). Sans pool (ou pool vide selon la politique du plan), la génération à la volée
 * de RC-5E reste le repli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_pool_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();

            $table->string('license_key', 190);
            $table->string('status', 16)->default('available'); // available | assigned
            $table->uuid('entitlement_id')->nullable();          // accès qui a consommé la clé

            $table->uuid('imported_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'license_key'], 'license_pool_scope_unique');
            $table->index(['tenant_id', 'product_id', 'status'], 'license_pool_tenant_product_status_idx');
        });

        // Alerte d'épuisement du pool (best-effort, canal du tenant).
        DB::table('notification_templates')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => null,
            'code' => 'digital.pool_exhausted', 'channel' => 'email', 'locale' => 'fr',
            'subject' => 'Pool de licences épuisé — {{product_name}}',
            'body' => "Bonjour {{tenant_name}},\n\nLe stock de clés de licence importées pour « {{product_name}} » est épuisé.\nComportement actuel : {{behavior}}.\nImportez de nouvelles clés depuis la fiche produit pour continuer à livrer des clés éditeur.\n\nFrynov",
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('license_pool_keys');
        DB::table('notification_templates')->whereNull('tenant_id')->where('code', 'digital.pool_exhausted')->delete();
    }
};

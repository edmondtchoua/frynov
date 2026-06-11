<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5E (produits spéciaux — digital) — DROIT D'ACCÈS accordé au client après la vente d'un produit
 * immatériel (téléchargement / licence). L'accès au contenu n'est JAMAIS un chemin de fichier exposé
 * mais passe par un `access_token` opaque, vérifié serveur (tenant + statut + expiration).
 *
 * Pas de stock pour ces produits (`stock_tracking=none`) : l'entitlement EST la preuve de livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('order_id');
            $table->uuid('order_line_id');
            $table->uuid('customer_id')->nullable();

            $table->string('fulfillment_type', 16);          // download | license (snapshot)
            $table->uuid('access_token');                    // jeton opaque d'accès (jamais le fichier)
            $table->string('license_key', 64)->nullable();   // clé générée si fulfillment=license

            // active | revoked | expired
            $table->string('status', 16)->default('active');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();      // null = perpétuel
            $table->timestamp('revoked_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Le jeton d'accès est unique GLOBALEMENT (sert de clé de recherche d'accès).
            $table->unique('access_token', 'digital_entitlements_access_token_unique');
            $table->index(['tenant_id', 'customer_id'], 'digital_entitlements_tenant_customer_idx');
            $table->index(['tenant_id', 'order_id'], 'digital_entitlements_tenant_order_idx');
            $table->index(['tenant_id', 'status'], 'digital_entitlements_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_entitlements');
    }
};

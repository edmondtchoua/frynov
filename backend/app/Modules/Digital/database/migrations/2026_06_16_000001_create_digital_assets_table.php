<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5I (produits digitaux — fichiers) — fichier PRIVÉ rattaché à un produit digital (le contenu
 * vendu : ebook, installeur, média…). Stocké sur un disque privé ; jamais exposé par son chemin. Le
 * client n'y accède que via un **lien de téléchargement signé et expirable** dérivé de son entitlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();

            $table->string('name', 200);                 // nom d'affichage / fichier d'origine
            $table->string('disk', 32)->default('local'); // disque privé
            $table->string('path', 512);                 // chemin de stockage (jamais exposé)
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime', 128)->nullable();
            $table->string('checksum', 64)->nullable();  // sha256 (intégrité)
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'product_id', 'is_active'], 'digital_assets_tenant_product_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_assets');
    }
};

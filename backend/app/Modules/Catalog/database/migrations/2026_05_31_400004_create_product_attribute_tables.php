<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 — Normalised product attribute system.
 *
 * product_attributes         : axes  (Couleur, RAM, Taille...)
 * product_attribute_values   : vals  (Rouge, 8Go, XL...)
 * product_variant_attr_values: pivot variant ↔ value (M:N)
 *
 * Replaces the unqueryable JSON blob on product_variants.attributes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Axis definitions per product
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id')->index();
            $table->string('name', 100);       // "Couleur", "RAM"
            $table->string('code', 50);        // "color", "ram"
            $table->string('type', 20)->default('select'); // select|text|number|boolean|color_swatch
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'code']);
            $table->index(['tenant_id', 'product_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Possible values for each axis
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id')->index();
            $table->string('label', 100);      // "Rouge"
            $table->string('value', 100);      // "red" (machine code)
            $table->string('color_hex', 7)->nullable();  // "#ff0000" for swatches
            $table->string('image_url', 512)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
            $table->foreign('attribute_id')->references('id')->on('product_attributes')->onDelete('cascade');
        });

        // Pivot: variant → values (one row per axis per variant)
        Schema::create('product_variant_attr_values', function (Blueprint $table) {
            $table->uuid('variant_id');
            $table->uuid('attribute_value_id');

            $table->primary(['variant_id', 'attribute_value_id']);
            $table->index('attribute_value_id', 'pvav_attr_val_idx');

            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->foreign('attribute_value_id')->references('id')->on('product_attribute_values')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attr_values');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};

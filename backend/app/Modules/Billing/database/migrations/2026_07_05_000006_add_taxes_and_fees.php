<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Option — taxes & frais d'installation. `tax_rate_bps` = taux en points de base (1800 = 18 %),
 * `setup_fee_minor` = frais UNIQUE à la souscription (unités mineures, convention ×100). Figés sur la
 * demande (`subscription_change_requests`) pour la traçabilité et le règlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('tax_rate_bps')->default(0)->after('badge');   // TVA/TPS en points de base
            $table->unsignedBigInteger('setup_fee_minor')->default(0)->after('tax_rate_bps'); // frais unique
        });

        Schema::table('subscription_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_minor')->default(0)->after('promo_discount_minor');
            $table->unsignedBigInteger('setup_fee_minor')->default(0)->after('tax_minor');
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['tax_rate_bps', 'setup_fee_minor']));
        Schema::table('subscription_change_requests', fn (Blueprint $t) => $t->dropColumn(['tax_minor', 'setup_fee_minor']));
    }
};

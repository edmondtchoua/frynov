<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-16 — Cash drawer movements (mouvements de caisse).
 *
 * Every non-sale cash event that touches the drawer during a session: a pay-in
 * (approvisionnement / change added) or a pay-out (retrait, dépense, remboursement
 * espèces). Kept out of `total_sales_cents` so day-end reconciliation stays exact:
 * expected cash = opening float + cash sales + pay-ins − pay-outs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('session_id')->index();                   // cash_register_sessions.id

            // 'in' = argent ajouté au tiroir · 'out' = argent retiré du tiroir
            $table->enum('direction', ['in', 'out'])->index();

            $table->integer('amount_cents');                        // > 0, always positive
            $table->string('reason', 100);                          // 'float_add','withdrawal','expense','refund',...
            $table->text('note')->nullable();

            // When a movement is the cash leg of a refund, link back to the order for the audit trail.
            $table->uuid('order_id')->nullable()->index();

            $table->uuid('performed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};

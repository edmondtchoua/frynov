<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-30 (P3 compta) — FACTURATION client + allocations de paiement.
 *
 * Facture = document + génération d'écriture à l'émission (débit 411 client, crédit 701 HT + 4431
 * TVA). Un paiement peut être alloué à PLUSIEURS factures (payment_allocations, N↔N) ; le solde
 * d'une facture pilote son statut (issued → partially_paid → paid). Montants en centimes entiers ;
 * taux de remise en points de base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('number', 24)->nullable();            // FA-… assigné à l'émission
            $table->string('kind', 16)->default('invoice');      // invoice | deposit | final
            $table->uuid('customer_id')->nullable()->index();
            $table->uuid('order_id')->nullable()->index();       // facture issue d'une commande
            $table->uuid('proforma_id')->nullable()->index();    // issue d'une proforma (P3.2)
            $table->char('currency', 3)->default('XOF');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');      // draft|issued|partially_paid|paid|cancelled
            $table->unsignedBigInteger('subtotal_minor')->default(0);  // HT
            $table->unsignedBigInteger('tax_total_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);     // TTC
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->uuid('entry_id')->nullable();                // écriture d'émission
            $table->string('customer_name')->nullable();         // snapshot (le client peut évoluer)
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('issued_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'invoices_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('invoice_id')->index();
            $table->uuid('product_id')->nullable();
            $table->string('label');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0); // HT
            $table->unsignedInteger('discount_bp')->default(0);         // remise ligne (points de base)
            $table->uuid('tax_id')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);   // HT après remise
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);      // TTC ligne
            $table->timestamps();
        });

        // Allocation N↔N paiement ↔ facture (paiement partiel, multiple, trop-perçu).
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('payment_id')->index();
            $table->uuid('invoice_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->uuid('entry_id')->nullable();                // écriture d'encaissement (tréso/411)
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id'], 'pay_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};

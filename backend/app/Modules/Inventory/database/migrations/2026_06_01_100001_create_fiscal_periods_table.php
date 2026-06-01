<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Axe 4 — Période fiscale et verrouillage comptable.
 * State machine: open → review → locked (irréversible).
 * Une fois locked, aucune écriture n'est possible sur la période.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('fiscal_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->string('name', 150);
            $t->string('type', 20);          // annual|quarterly|monthly
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status', 20)->default('open'); // open|review|locked
            $t->uuid('locked_by')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->text('lock_reason')->nullable();
            $t->bigInteger('total_value_cents_at_lock')->nullable();
            $t->string('integrity_hash', 64)->nullable();
            $t->timestamps();

            $t->unique(['tenant_id','starts_at','ends_at','type'], 'fp_tenant_period_unique');
            $t->index(['tenant_id','status'],    'fp_tenant_status_idx');
            $t->index(['tenant_id','ends_at'],   'fp_tenant_ends_idx');
        });

        // MySQL double-lock trigger
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_stock_movements_period_lock
                BEFORE INSERT ON stock_movements FOR EACH ROW
                BEGIN
                    DECLARE cnt INT DEFAULT 0;
                    SELECT COUNT(*) INTO cnt FROM fiscal_periods
                    WHERE tenant_id = NEW.tenant_id
                      AND status    = 'locked'
                      AND starts_at <= DATE(NEW.created_at)
                      AND ends_at   >= DATE(NEW.created_at);
                    IF cnt > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'PERIOD_LOCKED: Cannot write to a locked fiscal period';
                    END IF;
                END
            ");
        }
    }

    public function down(): void {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_movements_period_lock');
        }
        Schema::dropIfExists('fiscal_periods');
    }
};

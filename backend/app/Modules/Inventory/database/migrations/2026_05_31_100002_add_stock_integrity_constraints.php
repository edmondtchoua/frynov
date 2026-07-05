<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds CHECK constraints to the stocks table to enforce business rules at the
 * database level:
 *   - quantity >= 0
 *   - reserved_quantity >= 0
 *   - quantity >= reserved_quantity  (available stock can never be < 0)
 *
 * Also adds constraints to stock_movements to ensure audit trail integrity.
 *
 * NOTE: MySQL 8.0.16+ enforces CHECK constraints. MariaDB 10.2.1+.
 * For older MariaDB a BEFORE UPDATE trigger is also added as a safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used by tests) does not support ALTER TABLE ... ADD CONSTRAINT CHECK.
        // These constraints are MySQL/MariaDB production-only — skip on SQLite.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // ── 1. Fix any pre-existing incoherent rows before adding constraints ──
        DB::statement('UPDATE stocks SET reserved_quantity = quantity WHERE reserved_quantity > quantity');

        // ── 2. stocks table ───────────────────────────────────────────────────
        DB::statement('ALTER TABLE stocks
            ADD CONSTRAINT chk_stocks_qty_non_negative
                CHECK (quantity >= 0),
            ADD CONSTRAINT chk_stocks_reserved_non_negative
                CHECK (reserved_quantity >= 0),
            ADD CONSTRAINT chk_stocks_available_non_negative
                CHECK (quantity >= reserved_quantity)
        ');

        // ── 3. stock_movements audit trail integrity ──────────────────────────
        DB::statement('ALTER TABLE stock_movements
            ADD CONSTRAINT chk_movement_qty_positive
                CHECK (quantity > 0),
            ADD CONSTRAINT chk_movement_before_non_negative
                CHECK (quantity_before >= 0),
            ADD CONSTRAINT chk_movement_after_non_negative
                CHECK (quantity_after >= 0)
        ');

        // ── 4. MariaDB safety net: BEFORE UPDATE trigger ──────────────────────
        try {
            DB::unprepared("
                CREATE TRIGGER trg_stocks_integrity_before_update
                BEFORE UPDATE ON stocks FOR EACH ROW
                BEGIN
                    IF NEW.quantity < 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'STOCK_NEGATIVE: quantity cannot be < 0';
                    END IF;
                    IF NEW.reserved_quantity > NEW.quantity THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'STOCK_OVERRESERVED: reserved_quantity > quantity';
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            // Trigger creation may fail on some MySQL 8+ setups — CHECK constraints
            // are sufficient on those versions.
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_stocks_integrity_before_update');
        } catch (\Exception $e) {}

        foreach ([
            'chk_stocks_qty_non_negative',
            'chk_stocks_reserved_non_negative',
            'chk_stocks_available_non_negative',
        ] as $constraint) {
            try { DB::statement("ALTER TABLE stocks DROP CHECK {$constraint}"); } catch (\Exception $e) {}
        }

        foreach ([
            'chk_movement_qty_positive',
            'chk_movement_before_non_negative',
            'chk_movement_after_non_negative',
        ] as $constraint) {
            try { DB::statement("ALTER TABLE stock_movements DROP CHECK {$constraint}"); } catch (\Exception $e) {}
        }
    }
};

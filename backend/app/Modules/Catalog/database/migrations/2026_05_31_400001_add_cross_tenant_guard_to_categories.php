<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0 — Adds cross-tenant guard to categories:
 *   1. Composite indexes for tree traversal performance
 *   2. depth + path columns for materialised path (avoids O(N) recursive queries)
 *   3. MySQL trigger blocking cross-tenant parent_id assignment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->index(['tenant_id', 'parent_id'],               'cat_tenant_parent_idx');
            $table->index(['tenant_id', 'is_active', 'sort_order'], 'cat_tenant_active_sort_idx');
            $table->unsignedTinyInteger('depth')->default(0)->after('sort_order');
            // path stores UUID chain: "uuid1/uuid2/.../uuidN"
            // 500 chars = ~13 levels deep (UUID=36 + separator=1 each)
            $table->string('path', 500)->nullable()->after('depth');
        });

        // Prefix index of 191 chars (191×4=764 bytes < 1000 byte MySQL limit)
        // Still fully functional for LIKE 'root-uuid%' queries on the first 5 levels
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('CREATE INDEX cat_path_idx ON categories (path(191))');
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_categories_cross_tenant_before_insert
                BEFORE INSERT ON categories FOR EACH ROW
                BEGIN
                    IF NEW.parent_id IS NOT NULL THEN
                        SET @parent_tenant = (
                            SELECT tenant_id FROM categories
                            WHERE id = NEW.parent_id AND deleted_at IS NULL
                            LIMIT 1
                        );
                        IF @parent_tenant IS NULL OR @parent_tenant != NEW.tenant_id THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'CROSS_TENANT_CATEGORY: parent belongs to another tenant';
                        END IF;
                    END IF;
                END
            ");
            DB::unprepared("
                CREATE TRIGGER trg_categories_cross_tenant_before_update
                BEFORE UPDATE ON categories FOR EACH ROW
                BEGIN
                    IF NEW.parent_id IS NOT NULL AND NEW.parent_id != OLD.parent_id THEN
                        SET @parent_tenant = (
                            SELECT tenant_id FROM categories
                            WHERE id = NEW.parent_id AND deleted_at IS NULL
                            LIMIT 1
                        );
                        IF @parent_tenant IS NULL OR @parent_tenant != NEW.tenant_id THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'CROSS_TENANT_CATEGORY: parent belongs to another tenant';
                        END IF;
                    END IF;
                END
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_categories_cross_tenant_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_categories_cross_tenant_before_update');
        }
        if (DB::getDriverName() !== 'sqlite') {
            try { DB::statement('DROP INDEX cat_path_idx ON categories'); } catch (\Exception $e) {}
        }

        Schema::table('categories', function (Blueprint $table) {
            try { $table->dropIndex('cat_tenant_parent_idx'); } catch (\Exception $e) {}
            try { $table->dropIndex('cat_tenant_active_sort_idx'); } catch (\Exception $e) {}
            $table->dropColumn(['depth', 'path']);
        });
    }
};

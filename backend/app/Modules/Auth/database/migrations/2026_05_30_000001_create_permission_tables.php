<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spatie Laravel Permission — adapted for UUID primary keys and tenant_id team scoping.
return new class extends Migration
{
    public function up(): void
    {
        $teams      = config('permission.teams');
        $teamColumn = config('permission.column_names.team_foreign_key', 'tenant_id');
        $morphKey   = config('permission.column_names.model_morph_key', 'model_uuid');

        // MySQL / MariaDB utf8mb4 index-length budget (max 1000 bytes composite):
        //   CHAR(36) uuid   = 36 × 4 = 144 bytes
        //   string(100)     = 100 × 4 = 400 bytes
        //   BIGINT          =           8 bytes
        //
        // Worst-case composite (teams): tenant_id(144) + role_id(8) + uuid(144) + model_type(400) = 696 bytes ✓

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);       // 100 × 4 = 400 bytes
            $table->string('guard_name', 100); // 100 × 4 = 400 bytes — total: 800 < 1000 ✓
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) use ($teams, $teamColumn) {
            $table->bigIncrements('id');

            if ($teams) {
                $table->uuid($teamColumn)->nullable()->index(); // 144 bytes
            }

            $table->string('name', 100);       // 400 bytes
            $table->string('guard_name', 100); // 400 bytes
            $table->timestamps();

            if ($teams) {
                // 144 + 400 + 400 = 944 bytes ✓
                $table->unique([$teamColumn, 'name', 'guard_name']);
            } else {
                // 400 + 400 = 800 bytes ✓
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create('model_has_permissions', function (Blueprint $table) use ($teams, $teamColumn, $morphKey) {
            // MySQL forbids nullable columns in PRIMARY KEY.
            // When teams is enabled, tenant_id is nullable → use surrogate PK + UNIQUE instead.
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type', 100); // 400 bytes
            $table->uuid($morphKey);           // 144 bytes
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            if ($teams) {
                $table->uuid($teamColumn)->nullable();
                $table->index($teamColumn, 'model_has_permissions_team_foreign_key_index');
                // UNIQUE (not PK) so nullable tenant_id is allowed by MySQL
                // 144 + 8 + 144 + 400 = 696 bytes ✓
                $table->unique([$teamColumn, 'permission_id', $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                // No nullable column → real PK is fine
                // 8 + 144 + 400 = 552 bytes ✓
                $table->unique(['permission_id', $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create('model_has_roles', function (Blueprint $table) use ($teams, $teamColumn, $morphKey) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->string('model_type', 100); // 400 bytes
            $table->uuid($morphKey);           // 144 bytes
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            if ($teams) {
                $table->uuid($teamColumn)->nullable();
                $table->index($teamColumn, 'model_has_roles_team_foreign_key_index');
                // UNIQUE instead of PK to allow nullable tenant_id on MySQL
                // 144 + 8 + 144 + 400 = 696 bytes ✓
                $table->unique([$teamColumn, 'role_id', $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            } else {
                $table->unique(['role_id', $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->primary(['permission_id', 'role_id']);
        });

        app('cache')->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::drop('role_has_permissions');
        Schema::drop('model_has_roles');
        Schema::drop('model_has_permissions');
        Schema::drop('roles');
        Schema::drop('permissions');
    }
};

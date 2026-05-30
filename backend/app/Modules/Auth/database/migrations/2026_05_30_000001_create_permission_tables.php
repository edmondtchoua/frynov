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

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) use ($teams, $teamColumn) {
            $table->bigIncrements('id');

            if ($teams) {
                $table->uuid($teamColumn)->nullable()->index();
            }

            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams) {
                $table->unique([$teamColumn, 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create('model_has_permissions', function (Blueprint $table) use ($teams, $teamColumn, $morphKey) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            if ($teams) {
                $table->uuid($teamColumn)->nullable();
                $table->index($teamColumn, 'model_has_permissions_team_foreign_key_index');
                $table->primary([$teamColumn, 'permission_id', $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary(['permission_id', $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create('model_has_roles', function (Blueprint $table) use ($teams, $teamColumn, $morphKey) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            if ($teams) {
                $table->uuid($teamColumn)->nullable();
                $table->index($teamColumn, 'model_has_roles_team_foreign_key_index');
                $table->primary([$teamColumn, 'role_id', $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary(['role_id', $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
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

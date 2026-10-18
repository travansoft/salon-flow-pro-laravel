<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);

            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Null for platform-wide system roles, set for a tenant\'s custom roles');

            $table->string('description')->nullable()->after('guard_name')->comment('Shown to tenants when choosing a role');

            $table->unique(['tenant_id', 'name', 'guard_name']);
        });
    }
};

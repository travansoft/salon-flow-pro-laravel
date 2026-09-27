<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * branch_id is nullable for now — backfilled by a dedicated data
     * migration, then constrained NOT NULL once every existing row has a
     * branch assigned. Per-tenant uniqueness on these catalog tables is
     * widened to per-(tenant, branch) so the same code/name can be reused
     * across a tenant's branches.
     */
    public function up(): void
    {
        $plainTables = [
            'service_categories',
            'inventory_categories',
            'commission_rates',
            'service_price_histories',
        ];

        foreach ($plainTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
                $table->index(['tenant_id', 'branch_id']);
            });
        }

        Schema::table('service_categories', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'branch_id', 'name']);
        });

        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'branch_id', 'name']);
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'branch_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'branch_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('drop index if exists services_tenant_id_code_unique');
            DB::statement('drop index if exists products_tenant_id_sku_unique');
        } else {
            DB::statement('alter table services drop constraint if exists services_tenant_id_code_unique');
            DB::statement('drop index if exists services_tenant_id_code_unique');
            DB::statement('drop index if exists products_tenant_id_sku_unique');
        }

        DB::statement(
            'create unique index if not exists services_tenant_id_branch_id_code_unique on services (tenant_id, branch_id, code) where deleted_at is null'
        );

        DB::statement(
            'create unique index if not exists products_tenant_id_branch_id_sku_unique on products (tenant_id, branch_id, sku) where deleted_at is null and sku is not null'
        );
    }
};

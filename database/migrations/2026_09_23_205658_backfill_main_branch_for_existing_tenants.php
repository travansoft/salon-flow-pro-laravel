<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates one "Main Branch" per existing tenant, assigns every existing
     * user of that tenant to it, and backfills branch_id on every row that
     * gained the column in the preceding schema migrations.
     */
    public function up(): void
    {
        $branchScopedTables = [
            'appointments', 'bills', 'bill_line_items', 'walk_ins', 'time_slots',
            'staff_shifts', 'staff_leave_requests', 'bridal_engagements',
            'appointment_reminders', 'appointment_status_histories', 'expenses',
            'stock_adjustments', 'service_product_usages', 'staff_incentives',
            'services', 'service_categories', 'products', 'inventory_categories',
            'commission_rates', 'service_price_histories',
        ];

        DB::table('tenants')->orderBy('id')->select('id')->cursor()->each(function (object $tenant) use ($branchScopedTables): void {
            $branchId = DB::table('branches')->insertGetId([
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
                'slug' => 'main',
                'invoice_prefix' => 'INV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('user_branch')->insertUsing(
                ['user_id', 'branch_id', 'created_at', 'updated_at'],
                DB::table('users')
                    ->where('tenant_id', $tenant->id)
                    ->selectRaw('id, ? as branch_id, ? as created_at, ? as updated_at', [$branchId, now(), now()])
            );

            foreach ($branchScopedTables as $tableName) {
                DB::table($tableName)->where('tenant_id', $tenant->id)->update(['branch_id' => $branchId]);
            }
        });
    }
};

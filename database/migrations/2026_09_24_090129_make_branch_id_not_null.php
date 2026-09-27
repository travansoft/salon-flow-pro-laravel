<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Run only once the backfill migration has populated every existing
     * row's branch_id and BranchContext/BranchScope are wired up to
     * populate it on every new write.
     */
    public function up(): void
    {
        $tables = [
            'appointments', 'bills', 'bill_line_items', 'walk_ins', 'time_slots',
            'staff_shifts', 'staff_leave_requests', 'bridal_engagements',
            'appointment_reminders', 'appointment_status_histories', 'expenses',
            'stock_adjustments', 'service_product_usages', 'staff_incentives',
            'services', 'service_categories', 'products', 'inventory_categories',
            'commission_rates', 'service_price_histories',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable(false)->change();
            });
        }
    }
};

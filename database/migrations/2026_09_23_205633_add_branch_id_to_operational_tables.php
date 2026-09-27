<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable for now — backfilled by a dedicated data migration, then
     * constrained NOT NULL once every existing row has a branch assigned.
     */
    public function up(): void
    {
        $tables = [
            'appointments',
            'bills',
            'bill_line_items',
            'walk_ins',
            'time_slots',
            'staff_shifts',
            'staff_leave_requests',
            'bridal_engagements',
            'appointment_reminders',
            'appointment_status_histories',
            'expenses',
            'stock_adjustments',
            'service_product_usages',
            'staff_incentives',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
                $table->index(['tenant_id', 'branch_id']);
            });
        }
    }
};

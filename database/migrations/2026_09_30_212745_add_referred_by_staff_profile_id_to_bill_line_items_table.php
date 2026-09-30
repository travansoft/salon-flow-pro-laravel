<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bill_line_items', function (Blueprint $table): void {
            $table->foreignId('referred_by_staff_profile_id')->nullable()->after('staff_profile_id')
                ->constrained('staff_profiles')->nullOnDelete()
                ->comment('Staff member who canvassed the client for this service; null means direct');

            $table->index(['tenant_id', 'referred_by_staff_profile_id']);
        });
    }
};

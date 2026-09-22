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
        Schema::create('super_admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Who performed the action; null if the acting admin was later removed');
            $table->string('platform_admin_name')->comment('Snapshot of the actor name at the time of the action');
            $table->string('action')->comment('e.g. tenant.created, tenant.deactivated, tenant_user.created');
            $table->string('subject_type')->nullable()->comment('Model class of the affected record');
            $table->unsignedBigInteger('subject_id')->nullable()->comment('Primary key of the affected record');
            $table->string('description')->comment('Human-readable summary shown in the activity log');
            $table->timestamps();

            $table->index(['platform_admin_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['created_at']);
        });
    }
};

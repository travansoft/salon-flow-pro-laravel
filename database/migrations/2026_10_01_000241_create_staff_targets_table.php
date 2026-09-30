<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->date('month')->comment('First day of the target month');
            $table->decimal('target_amount', 12, 2)->comment('Monthly target, GST-inclusive service value');
            $table->timestamps();

            $table->unique(['tenant_id', 'staff_profile_id', 'month']);
            $table->index(['tenant_id', 'month']);

            $table->comment('Monthly incentive target per staff member');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_slabs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_achievement_percent', 6, 2)->comment('Target achievement at which this slab starts');
            $table->decimal('incentive_percent', 5, 2)->comment('Percent of the total achieved amount paid as incentive');
            $table->timestamps();

            $table->unique(['tenant_id', 'min_achievement_percent']);

            $table->comment('Achievement slabs that decide the incentive percent');
        });
    }
};

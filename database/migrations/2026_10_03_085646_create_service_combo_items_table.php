<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_combo_items', function (Blueprint $table): void {
            $table->comment('Component services (with prices) that make up a combo service');
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('combo_service_id')->constrained('services')->cascadeOnDelete()->comment('The combo service');
            $table->foreignId('component_service_id')->constrained('services')->restrictOnDelete()->comment('A service included in the combo');
            $table->decimal('price', 10, 2)->comment('GST-inclusive component price within the combo');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['combo_service_id', 'component_service_id']);
            $table->index(['tenant_id', 'branch_id']);
            $table->index('component_service_id');
        });
    }
};

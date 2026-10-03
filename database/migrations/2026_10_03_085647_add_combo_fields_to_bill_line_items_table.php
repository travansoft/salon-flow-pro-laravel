<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_line_items', function (Blueprint $table): void {
            $table->foreignId('combo_service_id')->nullable()->after('service_id')->constrained('services')->nullOnDelete()->comment('Combo this line was expanded from');
            $table->string('combo_group', 36)->nullable()->after('combo_service_id')->comment('Shared id for all lines of one billed combo');

            $table->index(['bill_id', 'combo_group']);
        });
    }
};

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
        Schema::table('services', function (Blueprint $table): void {
            $table->decimal('tax_rate', 5, 2)->nullable()->comment('Per-service GST percentage override, null uses tenant default');
            $table->string('hsn_sac_code', 10)->nullable()->comment('HSN code for goods or SAC code for services');
        });
    }
};

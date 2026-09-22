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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('gst_number', 15)->nullable()->unique()->comment('GSTIN, 15-character format');
            $table->string('gst_state_code', 2)->nullable()->comment('First 2 digits of GSTIN, used for CGST/SGST vs IGST determination');
            $table->string('legal_name')->nullable()->comment('Registered business name for invoice header, falls back to name');
            $table->text('address')->nullable()->comment('Registered business address for invoice header');
            $table->decimal('default_gst_rate', 5, 2)->default(18.00)->comment('Fallback GST percentage when a service has no rate set');
        });
    }
};

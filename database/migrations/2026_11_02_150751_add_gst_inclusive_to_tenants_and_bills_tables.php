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
            $table->boolean('gst_inclusive')->default(true)->comment('true: service prices already include GST; false: GST is added on top of the price');
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->boolean('prices_include_gst')->default(true)->comment('Snapshot of the tenant GST mode when the bill was created; true: prices included GST, false: GST was added on top');
        });
    }
};

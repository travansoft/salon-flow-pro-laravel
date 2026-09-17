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
            $table->mediumText('print_logo')->nullable()->comment('Base64 data URI, shown on thermal print receipts');
            $table->mediumText('ui_logo')->nullable()->comment('Base64 data URI, shown in the admin app UI');
        });
    }
};

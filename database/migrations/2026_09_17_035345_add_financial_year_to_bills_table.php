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
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'bill_number']);
            $table->string('financial_year', 7)->nullable()->after('bill_number')->comment('Indian financial year, e.g. 2026-27');
            $table->unique(['tenant_id', 'financial_year', 'bill_number']);
            $table->index(['tenant_id', 'financial_year']);
        });
    }
};

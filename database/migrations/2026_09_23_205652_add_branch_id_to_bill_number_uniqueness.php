<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoice numbers are now sequential per branch, not just per tenant.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'financial_year', 'bill_number']);
            $table->unique(['tenant_id', 'branch_id', 'financial_year', 'bill_number']);
        });
    }
};

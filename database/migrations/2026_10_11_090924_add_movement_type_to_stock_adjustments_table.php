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
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->string('type')->default('manual')->comment('manual|purchase|expired|damaged|usage|usage_reversal|count_correction');
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete()->comment('Bill that caused a usage or usage_reversal movement');
            $table->foreignId('purchase_id')->nullable()->constrained('stock_purchases')->nullOnDelete()->comment('Purchase that caused a purchase movement');

            $table->index(['tenant_id', 'product_id', 'type']);
            $table->index('bill_id');
        });
    }
};

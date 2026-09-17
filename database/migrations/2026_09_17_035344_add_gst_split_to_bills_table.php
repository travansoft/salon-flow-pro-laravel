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
            $table->decimal('cgst_amount', 10, 2)->default(0)->comment('Central GST, applied for intra-state sales');
            $table->decimal('sgst_amount', 10, 2)->default(0)->comment('State GST, applied for intra-state sales');
            $table->decimal('igst_amount', 10, 2)->default(0)->comment('Integrated GST, applied for inter-state sales');
        });

        Schema::table('bill_line_items', function (Blueprint $table): void {
            $table->decimal('cgst_amount', 10, 2)->default(0)->comment('Central GST, applied for intra-state sales');
            $table->decimal('sgst_amount', 10, 2)->default(0)->comment('State GST, applied for intra-state sales');
            $table->decimal('igst_amount', 10, 2)->default(0)->comment('Integrated GST, applied for inter-state sales');
        });
    }
};

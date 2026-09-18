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
            $table->decimal('discount_percent', 5, 2)->default(0)->comment('Whole-bill discount percentage, applied to the taxable amount before GST');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('Discount amount in currency, derived from discount_percent x pre-discount subtotal');
        });

        Schema::table('bill_line_items', function (Blueprint $table): void {
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('This line item share of the bill-level discount, applied before GST');
        });
    }
};

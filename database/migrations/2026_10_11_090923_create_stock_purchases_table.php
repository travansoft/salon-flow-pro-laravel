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
        Schema::create('stock_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->comment('Quantity received, in the product unit');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('Cost per unit paid to the supplier');
            $table->string('supplier_name')->nullable()->comment('Free-text supplier, no supplier master');
            $table->string('invoice_no')->nullable()->comment('Supplier invoice reference');
            $table->date('purchased_at')->comment('Date the stock was purchased or received');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id', 'purchased_at']);
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::table('stock_purchases', function (Blueprint $table): void {
            $table->comment('Simple stock purchase entries; each one increases the product quantity on hand');
        });
    }
};

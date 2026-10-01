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
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('payment_method')->default('cash')->after('amount')->comment('cash|upi|card, how the expense was paid out');
        });

        Schema::table('bill_refunds', function (Blueprint $table): void {
            $table->string('method')->default('cash')->after('amount')->comment('cash|upi|card, how the refund was paid out');
        });
    }
};

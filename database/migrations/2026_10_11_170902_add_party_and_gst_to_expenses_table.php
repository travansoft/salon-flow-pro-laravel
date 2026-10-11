<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('party_name')->nullable()->after('description')->comment('Vendor or party the expense was paid to');
            $table->string('gst_number', 15)->nullable()->after('party_name')->comment('GSTIN of the party');
            $table->decimal('base_amount', 10, 2)->default(0)->after('gst_number')->comment('Amount excluding GST');
            $table->decimal('gst_amount', 10, 2)->default(0)->after('base_amount')->comment('GST charged on the base amount');
        });

        DB::table('expenses')->update(['base_amount' => DB::raw('amount')]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_line_items', function (Blueprint $table): void {
            $table->decimal('target_amount', 10, 2)->nullable()->after('combo_group')->comment('Amount credited to staff targets for combo lines, GST not considered; null means the line value is used');
        });
    }
};

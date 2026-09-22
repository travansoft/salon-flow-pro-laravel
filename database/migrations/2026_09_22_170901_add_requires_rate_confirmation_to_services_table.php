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
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('requires_rate_confirmation')->default(false)->after('price')
                ->comment('Price varies by client (e.g. skin type, hair length); counter must confirm the rate given by the specialist before billing');
        });
    }
};

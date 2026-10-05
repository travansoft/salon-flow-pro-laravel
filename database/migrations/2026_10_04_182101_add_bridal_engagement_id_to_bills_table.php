<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('bridal_engagement_id')->nullable()->after('appointment_id')
                ->constrained()->nullOnDelete()
                ->comment('Bridal engagement this bill is attached to');

            $table->index(['tenant_id', 'bridal_engagement_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('is_combo')->default(false)->after('is_active')->comment('True when the service is a combo made of component services');
            $table->index(['tenant_id', 'is_combo']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_users')->nullable()->comment('Overrides tenancy.limits.max_users when set');
            $table->unsignedSmallInteger('max_branches')->nullable()->comment('Overrides tenancy.limits.max_branches when set');
        });
    }
};

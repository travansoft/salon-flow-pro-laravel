<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('check_staff_availability')->default(false)->comment('true: appointments are rejected when staff is outside shift hours or already booked; false: no availability check');
        });
    }
};

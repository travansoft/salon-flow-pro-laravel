<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('servicing_share_percent', 5, 2)->default(70)->comment('Share of a referred service value credited to the servicing staff');
            $table->decimal('referring_share_percent', 5, 2)->default(30)->comment('Share of a referred service value credited to the referring staff');
            $table->timestamps();

            $table->comment('Tenant-wide incentive configuration; the two shares always sum to 100');
        });
    }
};

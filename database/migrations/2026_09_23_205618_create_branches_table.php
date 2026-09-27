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
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Branch display name, e.g. "Kochi - Marine Drive"');
            $table->string('slug')->comment('Unique per tenant, used for branch-aware labelling/reporting');
            $table->string('invoice_prefix')->comment('Uppercase prefix used in this branch\'s invoice numbers, e.g. HSR');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('gst_state_code', 2)->nullable()->comment('Overrides the tenant default GST state code for this branch, if set');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'invoice_prefix']);
            $table->index(['tenant_id', 'is_active']);
        });
    }
};

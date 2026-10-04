<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()
                ->comment('Only this user can see, resume or discard the draft');
            $table->string('client_name')->nullable()->comment('Display only, copied from the payload');
            $table->string('client_phone', 30)->nullable()->comment('Display only, copied from the payload');
            $table->unsignedInteger('item_count')->default(0)->comment('Display only');
            $table->decimal('total', 10, 2)->default(0)->comment('Display only, GST-inclusive total at save time');
            $table->json('payload')->comment('Saved state of the new-bill form');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id', 'updated_at']);

            $table->comment('Per-user unsaved new-bill forms; not real bills and never numbered');
        });
    }
};

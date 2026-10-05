<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bridal_engagements', function (Blueprint $table) {
            $table->string('event_name')->nullable()->after('client_id')
                ->comment('Name of the event, e.g. wedding, reception');
            $table->string('venue_type')->default('studio')->after('event_date')
                ->comment('studio|home');
            $table->text('home_location')->nullable()->after('venue_type')
                ->comment('Address when venue_type is home');
            $table->boolean('has_studio_trial')->default(false)->after('home_location');
            $table->date('trial_date')->nullable()->after('has_studio_trial');
            $table->time('ready_time')->nullable()->after('trial_date')
                ->comment('Time the bride needs to be ready by');
            $table->decimal('total_amount', 10, 2)->default(0)->after('ready_time')
                ->comment('Agreed total for the event');
            $table->decimal('advance_amount', 10, 2)->default(0)->after('total_amount')
                ->comment('Advance received, informational only');
            $table->unsignedInteger('guest_makeup_count')->nullable()->after('advance_amount');
            $table->boolean('groom_makeup')->default(false)->after('guest_makeup_count');
            $table->string('dress_type')->nullable()->after('groom_makeup')
                ->comment('saree|others');
            $table->string('saree_drapist_name')->nullable()->after('dress_type');

            $table->index(['tenant_id', 'trial_date']);
        });
    }
};

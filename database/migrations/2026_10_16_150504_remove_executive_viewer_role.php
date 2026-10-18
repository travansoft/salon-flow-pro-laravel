<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->whereNull('tenant_id')->where('name', 'ExecutiveViewer')->delete();
    }
};

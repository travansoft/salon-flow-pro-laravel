<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            DB::table('permissions')
                ->where('name', "commissions.{$action}")
                ->update(['name' => "incentives.{$action}"]);
        }
    }
};

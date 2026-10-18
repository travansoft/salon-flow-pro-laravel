<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $alreadySeeded = DB::table('permissions')->where('name', "incentives.{$action}")->exists();

            if ($alreadySeeded) {
                DB::table('permissions')->where('name', "commissions.{$action}")->delete();

                continue;
            }

            DB::table('permissions')
                ->where('name', "commissions.{$action}")
                ->update(['name' => "incentives.{$action}"]);
        }
    }
};

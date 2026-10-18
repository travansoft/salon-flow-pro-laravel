<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        (new PermissionSeeder)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

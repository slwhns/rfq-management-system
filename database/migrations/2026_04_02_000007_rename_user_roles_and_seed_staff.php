<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','superadmin') NULL DEFAULT 'staff'");

        DB::table('users')->where('role', 'admin')->update(['role' => 'superadmin']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'admin']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('staff','admin','superadmin') NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('staff','admin','superadmin') NULL DEFAULT 'staff'");

        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'staff']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client','qs','admin','staff') NULL DEFAULT 'client'");
    }
};

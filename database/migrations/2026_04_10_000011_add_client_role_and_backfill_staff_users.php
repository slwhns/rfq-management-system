<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','client','staff') NULL DEFAULT 'client'");
        DB::table('users')->where('role', 'staff')->update(['role' => 'client']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'client')->update(['role' => 'staff']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','client','staff') NULL DEFAULT 'staff'");
    }
};

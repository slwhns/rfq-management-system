<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client','qs','admin','staff') NULL DEFAULT 'staff'");

        DB::table('users')->where('role', 'client')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'qs')->update(['role' => 'staff']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff') NULL DEFAULT 'staff'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client','qs','admin','staff') NULL DEFAULT 'client'");

        DB::table('users')->where('role', 'staff')->update(['role' => 'qs']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client','qs','admin') NULL DEFAULT 'client'");
    }
};

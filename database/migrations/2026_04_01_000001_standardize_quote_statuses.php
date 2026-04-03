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
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','in_progress','viewed','negotiation','cancelled') NULL DEFAULT 'in_progress'");

        DB::table('quotes')->where('status', 'draft')->update(['status' => 'in_progress']);
        DB::table('quotes')->where('status', 'sent')->update(['status' => 'viewed']);
        DB::table('quotes')->where('status', 'expired')->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('in_progress','viewed','negotiation','cancelled','accepted','rejected') NULL DEFAULT 'in_progress'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','in_progress','viewed','negotiation','cancelled') NULL DEFAULT 'draft'");

        DB::table('quotes')->where('status', 'in_progress')->update(['status' => 'draft']);
        DB::table('quotes')->where('status', 'viewed')->update(['status' => 'sent']);
        DB::table('quotes')->where('status', 'cancelled')->update(['status' => 'rejected']);
        DB::table('quotes')->where('status', 'negotiation')->update(['status' => 'sent']);

        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','sent','accepted','rejected') NULL DEFAULT 'draft'");
    }
};

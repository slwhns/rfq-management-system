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
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','in_progress','negotiation','approved','declined','cancelled','viewed','accepted','rejected') NULL DEFAULT 'draft'");

        DB::table('quotes')->where('status', 'sent')->update(['status' => 'in_progress']);
        DB::table('quotes')->where('status', 'viewed')->update(['status' => 'in_progress']);
        DB::table('quotes')->where('status', 'accepted')->update(['status' => 'approved']);
        DB::table('quotes')->where('status', 'rejected')->update(['status' => 'declined']);
        DB::table('quotes')->where('status', 'expired')->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','in_progress','negotiation','approved','declined','cancelled') NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('draft','in_progress','negotiation','approved','declined','cancelled','viewed','accepted','rejected') NULL DEFAULT 'in_progress'");

        DB::table('quotes')->where('status', 'approved')->update(['status' => 'accepted']);
        DB::table('quotes')->where('status', 'declined')->update(['status' => 'rejected']);

        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM('in_progress','viewed','negotiation','cancelled','accepted','rejected') NULL DEFAULT 'in_progress'");
    }
};

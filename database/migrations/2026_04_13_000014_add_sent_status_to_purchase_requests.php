<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY COLUMN status ENUM('draft','sent','in_progress','negotiation','approved','declined','cancelled') NULL DEFAULT 'draft'");

        DB::table('purchase_requests')
            ->whereIn('status', ['in_progress', 'negotiation'])
            ->update(['status' => 'sent']);
    }

    public function down(): void
    {
        DB::table('purchase_requests')
            ->where('status', 'sent')
            ->update(['status' => 'in_progress']);

        DB::statement("ALTER TABLE purchase_requests MODIFY COLUMN status ENUM('draft','in_progress','negotiation','approved','declined','cancelled') NULL DEFAULT 'draft'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('purchase_requests') || !Schema::hasTable('projects') || !Schema::hasTable('users')) {
            return;
        }

        DB::statement(
            "UPDATE purchase_requests pr
             INNER JOIN projects p ON p.id = pr.project_id
             SET pr.created_by = p.user_id
             WHERE pr.created_by IS NULL
               AND p.user_id IS NOT NULL"
        );

        DB::statement(
            "UPDATE purchase_requests pr
             INNER JOIN users u ON u.id = pr.created_by
             SET pr.department = u.department
             WHERE (pr.department IS NULL OR pr.department = '')
               AND u.department IS NOT NULL
               AND u.department <> ''"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: this migration only backfills missing data.
    }
};

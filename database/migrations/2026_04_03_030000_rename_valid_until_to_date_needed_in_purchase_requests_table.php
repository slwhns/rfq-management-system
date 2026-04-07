<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'valid_until') && !Schema::hasColumn('purchase_requests', 'date_needed')) {
            DB::statement('ALTER TABLE `purchase_requests` CHANGE `valid_until` `date_needed` DATETIME NULL AFTER `status`');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'date_needed') && !Schema::hasColumn('purchase_requests', 'valid_until')) {
            DB::statement('ALTER TABLE `purchase_requests` CHANGE `date_needed` `valid_until` DATETIME NULL AFTER `status`');
        }
    }
};

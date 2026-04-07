<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && !Schema::hasColumn('purchase_orders', 'supplier_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('company_name');
            });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasTable('suppliers') && Schema::hasColumn('purchase_orders', 'supplier_id')) {
            DB::statement('
                UPDATE purchase_orders po
                LEFT JOIN suppliers s
                    ON s.name COLLATE utf8mb4_unicode_ci = po.company_name COLLATE utf8mb4_unicode_ci
                    OR s.name COLLATE utf8mb4_unicode_ci = po.vendor_name COLLATE utf8mb4_unicode_ci
                SET po.supplier_id = s.id
                WHERE po.supplier_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'supplier_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('supplier_id');
            });
        }
    }
};

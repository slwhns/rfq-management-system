<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'discount_scope')) {
                $table->string('discount_scope', 20)->default('item')->after('discount_total');
            }

            if (!Schema::hasColumn('purchase_requests', 'discount_type')) {
                $table->string('discount_type', 20)->default('percent')->after('discount_scope');
            }

            if (!Schema::hasColumn('purchase_requests', 'discount_value')) {
                $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            }
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_request_items', 'discount_type')) {
                $table->string('discount_type', 20)->default('percent')->after('unit_price');
            }

            if (!Schema::hasColumn('purchase_request_items', 'discount_value')) {
                $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_request_items', 'discount_value')) {
                $table->dropColumn('discount_value');
            }

            if (Schema::hasColumn('purchase_request_items', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'discount_value')) {
                $table->dropColumn('discount_value');
            }

            if (Schema::hasColumn('purchase_requests', 'discount_type')) {
                $table->dropColumn('discount_type');
            }

            if (Schema::hasColumn('purchase_requests', 'discount_scope')) {
                $table->dropColumn('discount_scope');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'version')) {
                $table->integer('version')->default(1)->after('quote_number');
            }
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_request_items', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('unit_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }
};

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
        if (!Schema::hasTable('component_suppliers')) {
            return;
        }

        $columnsToDrop = [];

        if (Schema::hasColumn('component_suppliers', 'min_quantity')) {
            $columnsToDrop[] = 'min_quantity';
        }

        if (Schema::hasColumn('component_suppliers', 'max_quantity')) {
            $columnsToDrop[] = 'max_quantity';
        }

        if (Schema::hasColumn('component_suppliers', 'subscription_period')) {
            $columnsToDrop[] = 'subscription_period';
        }

        if (!empty($columnsToDrop)) {
            Schema::table('component_suppliers', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('component_suppliers')) {
            return;
        }

        Schema::table('component_suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('component_suppliers', 'min_quantity')) {
                $table->unsignedBigInteger('min_quantity')->nullable();
            }

            if (!Schema::hasColumn('component_suppliers', 'max_quantity')) {
                $table->unsignedBigInteger('max_quantity')->nullable();
            }

            if (!Schema::hasColumn('component_suppliers', 'subscription_period')) {
                $table->string('subscription_period', 100)->nullable();
            }
        });
    }
};

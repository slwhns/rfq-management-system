<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers')) {
            $this->addColumnsIfMissing('suppliers', [
                'address' => static fn (Blueprint $table) => $table->string('address', 255)->nullable()->after('name'),
                'phone' => static fn (Blueprint $table) => $table->string('phone', 50)->nullable()->after('address'),
            ]);
        }

        if (Schema::hasTable('component_suppliers')) {
            $this->addColumnsIfMissing('component_suppliers', [
                'category_id' => static fn (Blueprint $table) => $table->unsignedBigInteger('category_id')->nullable()->after('supplier_id'),
                'component_code' => static fn (Blueprint $table) => $table->string('component_code', 100)->nullable()->after('category_id'),
                'component_name' => static fn (Blueprint $table) => $table->string('component_name', 255)->nullable()->after('component_code'),
                'description' => static fn (Blueprint $table) => $table->text('description')->nullable()->after('component_name'),
                'unit' => static fn (Blueprint $table) => $table->string('unit', 50)->nullable()->after('description'),
                'currency' => static fn (Blueprint $table) => $table->string('currency', 10)->nullable()->after('unit'),
                'min_quantity' => static fn (Blueprint $table) => $table->unsignedBigInteger('min_quantity')->nullable()->after('currency'),
                'max_quantity' => static fn (Blueprint $table) => $table->unsignedBigInteger('max_quantity')->nullable()->after('min_quantity'),
                'is_smart_component' => static fn (Blueprint $table) => $table->boolean('is_smart_component')->default(false)->after('max_quantity'),
                'requires_license' => static fn (Blueprint $table) => $table->boolean('requires_license')->default(false)->after('is_smart_component'),
                'license_type' => static fn (Blueprint $table) => $table->string('license_type', 100)->nullable()->after('requires_license'),
                'subscription_period' => static fn (Blueprint $table) => $table->string('subscription_period', 100)->nullable()->after('license_type'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('component_suppliers')) {
            $this->dropExistingColumns('component_suppliers', [
                'category_id',
                'component_code',
                'component_name',
                'description',
                'unit',
                'currency',
                'min_quantity',
                'max_quantity',
                'is_smart_component',
                'requires_license',
                'license_type',
                'subscription_period',
            ]);
        }

        if (Schema::hasTable('suppliers')) {
            $this->dropExistingColumns('suppliers', ['address', 'phone']);
        }
    }

    private function addColumnsIfMissing(string $tableName, array $columnDefinitions): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName, $columnDefinitions) {
            foreach ($columnDefinitions as $column => $definition) {
                if (!Schema::hasColumn($tableName, $column)) {
                    $definition($table);
                }
            }
        });
    }

    private function dropExistingColumns(string $tableName, array $columns): void
    {
        $columnsToDrop = array_values(array_filter($columns, static fn ($column) => Schema::hasColumn($tableName, $column)));

        if (empty($columnsToDrop)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }
};

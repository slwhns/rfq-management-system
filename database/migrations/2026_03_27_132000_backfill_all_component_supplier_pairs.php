<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('components') || !Schema::hasTable('suppliers') || !Schema::hasTable('component_suppliers')) {
            return;
        }

        DB::statement(
            "
            INSERT INTO component_suppliers (
                component_id,
                supplier_id,
                category_id,
                component_code,
                component_name,
                description,
                unit,
                currency,
                min_quantity,
                max_quantity,
                is_smart_component,
                requires_license,
                license_type,
                subscription_period,
                price
            )
            SELECT
                c.id AS component_id,
                s.id AS supplier_id,
                c.category_id,
                c.component_code,
                c.component_name,
                c.description,
                c.unit,
                COALESCE(c.currency, 'RM') AS currency,
                c.min_quantity,
                c.max_quantity,
                COALESCE(c.is_smart_component, 0) AS is_smart_component,
                COALESCE(c.requires_license, 0) AS requires_license,
                c.license_type,
                c.subscription_period,
                COALESCE(
                    (
                        SELECT cs2.price
                        FROM component_suppliers cs2
                        WHERE cs2.component_id = c.id
                          AND cs2.price IS NOT NULL
                        LIMIT 1
                    ),
                    0
                ) AS price
            FROM components c
            CROSS JOIN suppliers s
            LEFT JOIN component_suppliers cs
                ON cs.component_id = c.id
                AND cs.supplier_id = s.id
            WHERE cs.id IS NULL
            "
        );
    }

    public function down(): void
    {
        // One-time data fill for missing component-company pairs; no rollback action.
    }
};

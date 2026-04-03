<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('component_suppliers') || !Schema::hasTable('components')) {
            return;
        }

        $rows = DB::table('component_suppliers as cs')
            ->join('components as c', 'c.id', '=', 'cs.component_id')
            ->select([
                'cs.id',
                'cs.category_id as cs_category_id',
                'cs.component_code as cs_component_code',
                'cs.component_name as cs_component_name',
                'cs.description as cs_description',
                'cs.unit as cs_unit',
                'cs.currency as cs_currency',
                'cs.min_quantity as cs_min_quantity',
                'cs.max_quantity as cs_max_quantity',
                'cs.is_smart_component as cs_is_smart_component',
                'cs.requires_license as cs_requires_license',
                'cs.license_type as cs_license_type',
                'cs.subscription_period as cs_subscription_period',
                'c.category_id as c_category_id',
                'c.component_code as c_component_code',
                'c.component_name as c_component_name',
                'c.description as c_description',
                'c.unit as c_unit',
                'c.currency as c_currency',
                'c.min_quantity as c_min_quantity',
                'c.max_quantity as c_max_quantity',
                'c.is_smart_component as c_is_smart_component',
                'c.requires_license as c_requires_license',
                'c.license_type as c_license_type',
                'c.subscription_period as c_subscription_period',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('component_suppliers')
                ->where('id', $row->id)
                ->update([
                    'category_id' => $row->cs_category_id ?? $row->c_category_id,
                    'component_code' => $row->cs_component_code ?? $row->c_component_code,
                    'component_name' => $row->cs_component_name ?? $row->c_component_name,
                    'description' => $row->cs_description ?? $row->c_description,
                    'unit' => $row->cs_unit ?? $row->c_unit,
                    'currency' => $row->cs_currency ?? $row->c_currency ?? 'RM',
                    'min_quantity' => $row->cs_min_quantity ?? $row->c_min_quantity,
                    'max_quantity' => $row->cs_max_quantity ?? $row->c_max_quantity,
                    'is_smart_component' => is_null($row->cs_is_smart_component)
                        ? (bool) $row->c_is_smart_component
                        : (bool) $row->cs_is_smart_component,
                    'requires_license' => is_null($row->cs_requires_license)
                        ? (bool) $row->c_requires_license
                        : (bool) $row->cs_requires_license,
                    'license_type' => $row->cs_license_type ?? $row->c_license_type,
                    'subscription_period' => $row->cs_subscription_period ?? $row->c_subscription_period,
                ]);
        }
    }

    public function down(): void
    {
        // Data backfill only; no down action required.
    }
};

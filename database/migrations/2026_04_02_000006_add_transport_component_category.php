<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('component_categories') || !Schema::hasColumn('component_categories', 'name')) {
            return;
        }

        $payload = ['name' => 'Transport'];

        if (Schema::hasColumn('component_categories', 'description')) {
            $payload['description'] = 'Delivered item to site';
        }

        if (Schema::hasColumn('component_categories', 'icon')) {
            $payload['icon'] = 'TR';
        }

        if (Schema::hasColumn('component_categories', 'sort_order')) {
            $maxSortOrder = (int) DB::table('component_categories')->max('sort_order');
            $payload['sort_order'] = $maxSortOrder + 1;
        }

        $now = now();
        if (Schema::hasColumn('component_categories', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn('component_categories', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        $existing = DB::table('component_categories')->where('name', 'Transport')->first();
        if (!$existing) {
            DB::table('component_categories')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('component_categories') || !Schema::hasColumn('component_categories', 'name')) {
            return;
        }

        DB::table('component_categories')
            ->where('name', 'Transport')
            ->delete();
    }
};

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
        Schema::table('project_components', function (Blueprint $table) {
            if (!Schema::hasColumn('project_components', 'project_id')) {
                $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('project_components', 'component_id')) {
                $table->foreignId('component_id')->nullable()->constrained('components')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('project_components', 'quantity')) {
                $table->integer('quantity')->default(1);
            }
            if (!Schema::hasColumn('project_components', 'custom_price')) {
                $table->decimal('custom_price', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('project_components', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_components', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['component_id']);
            $table->dropColumn(['project_id', 'component_id', 'quantity', 'custom_price', 'notes']);
        });
    }
};

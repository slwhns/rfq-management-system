<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'project_name')) {
                $table->string('project_name')->nullable();
            }
            if (!Schema::hasColumn('projects', 'project_title')) {
                $table->string('project_title')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'project_title')) {
                $table->dropColumn('project_title');
            }
        });
    }
};

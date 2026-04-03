<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'admin_notes_updated_at')) {
                $table->dateTime('admin_notes_updated_at')->nullable()->after('admin_notes');
            }

            if (!Schema::hasColumn('quotes', 'staff_response_updated_at')) {
                $table->dateTime('staff_response_updated_at')->nullable()->after('staff_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'admin_notes_updated_at')) {
                $table->dropColumn('admin_notes_updated_at');
            }

            if (Schema::hasColumn('quotes', 'staff_response_updated_at')) {
                $table->dropColumn('staff_response_updated_at');
            }
        });
    }
};

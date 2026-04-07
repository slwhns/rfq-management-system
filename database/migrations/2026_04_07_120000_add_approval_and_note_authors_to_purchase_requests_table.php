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
        if (!Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('purchase_requests', 'admin_notes_updated_by')) {
                $table->unsignedBigInteger('admin_notes_updated_by')->nullable()->after('admin_notes_updated_at');
                $table->foreign('admin_notes_updated_by')->references('id')->on('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('purchase_requests', 'staff_response_updated_by')) {
                $table->unsignedBigInteger('staff_response_updated_by')->nullable()->after('staff_response_updated_at');
                $table->foreign('staff_response_updated_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'staff_response_updated_by')) {
                $table->dropForeign(['staff_response_updated_by']);
                $table->dropColumn('staff_response_updated_by');
            }

            if (Schema::hasColumn('purchase_requests', 'admin_notes_updated_by')) {
                $table->dropForeign(['admin_notes_updated_by']);
                $table->dropColumn('admin_notes_updated_by');
            }

            if (Schema::hasColumn('purchase_requests', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }
        });
    }
};

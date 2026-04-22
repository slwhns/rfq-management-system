<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'quotation_sent_at')) {
                $table->dateTime('quotation_sent_at')->nullable()->after('staff_response_updated_by');
            }

            if (!Schema::hasColumn('purchase_requests', 'client_decision_note')) {
                $table->text('client_decision_note')->nullable()->after('quotation_sent_at');
            }

            if (!Schema::hasColumn('purchase_requests', 'client_decision_at')) {
                $table->dateTime('client_decision_at')->nullable()->after('client_decision_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'client_decision_at')) {
                $table->dropColumn('client_decision_at');
            }

            if (Schema::hasColumn('purchase_requests', 'client_decision_note')) {
                $table->dropColumn('client_decision_note');
            }

            if (Schema::hasColumn('purchase_requests', 'quotation_sent_at')) {
                $table->dropColumn('quotation_sent_at');
            }
        });
    }
};

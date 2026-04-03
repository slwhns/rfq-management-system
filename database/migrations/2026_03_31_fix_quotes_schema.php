<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Add project_id if missing
            if (!Schema::hasColumn('quotes', 'project_id')) {
                $table->unsignedBigInteger('project_id')->after('id');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            }
            
            // Add quote_number if missing
            if (!Schema::hasColumn('quotes', 'quote_number')) {
                $table->string('quote_number')->unique()->nullable()->after('project_id');
            }
            
            // Add pricing fields if missing
            if (!Schema::hasColumn('quotes', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('quote_number');
            }
            if (!Schema::hasColumn('quotes', 'discount_total')) {
                $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('quotes', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_total');
            }
            if (!Schema::hasColumn('quotes', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('quotes', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('quotes', 'status')) {
                $table->string('status')->default('in_progress')->after('total_amount');
            }
            if (!Schema::hasColumn('quotes', 'valid_until')) {
                $table->dateTime('valid_until')->nullable()->after('status');
            }
        });
        
        Schema::table('quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_items', 'quote_id')) {
                $table->unsignedBigInteger('quote_id')->after('id');
                $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
            }
            if (!Schema::hasColumn('quote_items', 'component_id')) {
                $table->unsignedBigInteger('component_id')->after('quote_id');
                $table->foreign('component_id')->references('id')->on('components')->onDelete('cascade');
            }
            if (!Schema::hasColumn('quote_items', 'quantity')) {
                $table->integer('quantity')->default(1)->after('component_id');
            }
            if (!Schema::hasColumn('quote_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->after('quantity');
            }
            if (!Schema::hasColumn('quote_items', 'line_total')) {
                $table->decimal('line_total', 12, 2)->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        // Reverse is not needed for this fix
    }
};

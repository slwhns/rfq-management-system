<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id')->unique();
            $table->unsignedBigInteger('client_user_id');
            $table->string('client_po_number', 120);
            $table->text('client_note')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('purchase_requests')->cascadeOnDelete();
            $table->foreign('client_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_purchase_orders');
    }
};


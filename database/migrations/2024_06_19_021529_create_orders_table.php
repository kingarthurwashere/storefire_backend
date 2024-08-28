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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->integer('woocommerce_order_id')->unique();
            $table->integer('total');
            $table->integer('balance');
            $table->enum('status', ['PENDING', 'PROCESSING', 'CANCELLED', 'PARTLY_PAID', 'COMPLETED', 'REFUNDED', 'FAILED'])->default('PENDING');
            $table->enum('payment_method', ['CASH_ON_DELIVERY'])->default('CASH_ON_DELIVERY');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

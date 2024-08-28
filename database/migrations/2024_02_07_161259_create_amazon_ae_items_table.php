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
        Schema::create('amazon_ae_items', function (Blueprint $table) {
            $table->id();
            $table->string('asin')->unique();
            $table->text('description')->nullable();
            $table->integer('price_aed')->nullable();
            $table->integer('price_usd')->nullable();
            $table->string('woocommerce_product_id');
            $table->string('file_id');
            $table->string('woocommerce_link')->unique();
            $table->longText('payload');
            $table->string('site_link')->unique();
            $table->string('url_hash')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_ae_items');
    }
};

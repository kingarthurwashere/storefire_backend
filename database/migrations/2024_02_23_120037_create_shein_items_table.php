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
        Schema::create('shein_items', function (Blueprint $table) {
            $table->id();
            $table->string('site_link');
            $table->string('url_hash')->unique();
            $table->text('description')->nullable();
            $table->integer('price_aed')->nullable();
            $table->integer('price_usd')->nullable();
            $table->string('woocommerce_product_id')->nullable();
            $table->string('url');
            $table->string('woocommerce_link')->nullable();
            $table->longText('payload');
            $table->string('file_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shein_items');
    }
};

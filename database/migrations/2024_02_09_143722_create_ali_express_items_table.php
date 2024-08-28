<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAliExpressItemsTable extends Migration
{
    public function up()
    {
        Schema::create('ali_express_items', function (Blueprint $table) {
            $table->id();
            $table->string('site_link');
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('price_aed')->nullable();
            $table->integer('price_usd')->nullable();
            $table->string('url_hash');
            $table->string('woocommerce_product_id');
            $table->string('url');
            $table->string('woocommerce_link');
            $table->longText('payload');
            $table->string('file_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ali_express_items');
    }
}

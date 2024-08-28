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
        Schema::create('config_active_shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedBigInteger('last_activated_by');
            $table->unsignedBigInteger('last_deactivated_by');
            $table->timestamps();

            $table->foreign('last_activated_by')->references('id')->on('users');
            $table->foreign('last_deactivated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_active_shops', function (Blueprint $table) {
            $table->dropForeign(['last_activated_by']);
            $table->dropForeign(['last_deactivated_by']);
        });
        Schema::dropIfExists('config_active_shops');
    }
};

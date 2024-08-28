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
        Schema::create('weight_information', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->decimal('determined_weight');
            $table->enum('store', ['ALIEXPRESS', 'AMAZON_AE', 'NOON']);
            $table->string('url', 755)->unique();
            $table->enum('weight_status', ['PENDING', 'ACCEPTABLE', 'UNDERWEIGHT', 'OVERWEIGHT'])->default('PENDING');
            $table->enum('weight_source', ['STORE', 'WEIGHT_MACHINE']);
            $table->enum('weight_machine_notes', ['LEVENSHTEIN', 'MACHINE_LEARNING', 'AMAZON'])->nullable();
            $table->enum('weight_accepted', ['YES', 'NO'])->nullable();
            $table->decimal('corrected_weight')->nullable();
            $table->decimal('weight_difference')->nullable();
            $table->unsignedBigInteger('corrected_weight_submitted_by')->nullable();
            $table->datetime('corrected_weight_submitted_at')->nullable();
            $table->longText('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_information');
    }
};

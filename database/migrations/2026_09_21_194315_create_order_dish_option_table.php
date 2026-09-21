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
        Schema::create('order_dish_option', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_dish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_option_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 8, 2);
            $table->timestamps();

            $table->unique(['order_dish_id', 'dish_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_dish_option');
    }
};

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
    Schema::create('order_items', function (Blueprint $table) {
        $table->id();
        $table->uuid('order_id');
        $table->uuid('product_id')->nullable();
        $table->uuid('variant_id')->nullable();
        $table->uuid('kit_id')->nullable();
        $table->integer('quantity');
        $table->decimal('unit_price', 10, 2);
        $table->decimal('total_price', 10, 2);
        $table->timestamps();

        $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_items');
    }
};

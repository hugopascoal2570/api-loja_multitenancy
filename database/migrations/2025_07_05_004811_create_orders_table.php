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
        $table->uuid('id')->primary();
        $table->string('order_number')->unique();
        $table->uuid('user_id');
        $table->decimal('total_amount', 10, 2);
        $table->string('payment_method');
        $table->string('payment_id')->nullable();
        $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'refunded']);
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

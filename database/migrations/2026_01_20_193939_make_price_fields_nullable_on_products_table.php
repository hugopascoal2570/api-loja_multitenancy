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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('retail_price', 10, 2)->nullable()->change();
            $table->decimal('wholesale_price', 10, 2)->nullable()->change();
            $table->integer('wholesale_min_qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('retail_price', 10, 2)->nullable(false)->change();
            $table->decimal('wholesale_price', 10, 2)->nullable(false)->change();
            $table->integer('wholesale_min_qty')->default(1)->nullable(false)->change();
        });
    }
};

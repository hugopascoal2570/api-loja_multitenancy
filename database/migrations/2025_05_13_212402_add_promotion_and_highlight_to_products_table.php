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
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_promotion')->default(false);
            $table->decimal('promotion_price', 10, 2)->nullable();
            $table->decimal('promotion_percent', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_highlighted', 'is_promotion', 'promotion_price', 'promotion_percent']);
        });
    }
};

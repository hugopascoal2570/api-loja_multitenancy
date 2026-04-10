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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->renameColumn('quantity', 'stock');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock')->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock')->nullable(false)->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->renameColumn('stock', 'quantity');
        });
    }
};

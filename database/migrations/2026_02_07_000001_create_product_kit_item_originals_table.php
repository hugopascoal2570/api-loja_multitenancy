<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_kit_item_originals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_kit_id');
            $table->uuid('variant_id');
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('product_kit_id')
                ->references('id')
                ->on('product_kits')
                ->onDelete('cascade');

            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kit_item_originals');
    }
};

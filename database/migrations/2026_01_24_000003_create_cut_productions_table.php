<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cut_productions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cut_id');
            $table->uuid('fabric_roll_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('product_variant_id')->nullable();
            $table->string('product_description')->nullable();
            $table->integer('quantity_produced');
            $table->decimal('fabric_meters_used', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cut_id')->references('id')->on('cuts')->onDelete('cascade');
            $table->foreign('fabric_roll_id')->references('id')->on('fabric_rolls')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cut_productions');
    }
};

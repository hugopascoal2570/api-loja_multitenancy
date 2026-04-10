<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->onDelete('cascade');
            $table->string('size', 10); // PP, P, M, G, GG, XG, etc.
            $table->decimal('bust', 6, 1)->nullable();       // Busto (cm)
            $table->decimal('waist', 6, 1)->nullable();      // Cintura (cm)
            $table->decimal('hip', 6, 1)->nullable();         // Quadril (cm)
            $table->decimal('length', 6, 1)->nullable();      // Comprimento (cm)
            $table->decimal('shoulder', 6, 1)->nullable();    // Ombro (cm)
            $table->decimal('sleeve', 6, 1)->nullable();      // Manga (cm)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_measurements');
    }
};

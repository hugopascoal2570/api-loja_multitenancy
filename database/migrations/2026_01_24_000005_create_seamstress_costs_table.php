<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seamstress_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('seamstress_id');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->enum('cost_type', ['per_piece', 'fixed'])->default('per_piece');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('seamstress_id')->references('id')->on('seamstresses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seamstress_costs');
    }
};

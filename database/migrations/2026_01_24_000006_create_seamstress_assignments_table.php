<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seamstress_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('seamstress_id');
            $table->uuid('cut_production_id');
            $table->integer('quantity_assigned');
            $table->integer('quantity_returned')->default(0);
            $table->integer('quantity_defective')->default(0);
            $table->decimal('price_per_piece', 10, 2);
            $table->enum('status', ['assigned', 'in_progress', 'returned'])->default('assigned');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('seamstress_id')->references('id')->on('seamstresses')->onDelete('cascade');
            $table->foreign('cut_production_id')->references('id')->on('cut_productions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seamstress_assignments');
    }
};

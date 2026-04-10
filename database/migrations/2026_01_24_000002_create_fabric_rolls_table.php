<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_rolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cut_id');
            $table->string('color', 100);
            $table->decimal('meters', 8, 2);
            $table->decimal('price_per_roll', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cut_id')->references('id')->on('cuts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
    }
};

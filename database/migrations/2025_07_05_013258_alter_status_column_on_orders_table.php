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
        Schema::table('orders', function (Blueprint $table) {
            // Altere o tipo de ENUM para string
            $table->string('status', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
        // Caso queira reverter, reimplante os valores possíveis do ENUM
        $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'refunded'])->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('label', 50)->nullable(); // Ex: "Casa", "Trabalho"
            $table->string('recipient_name')->nullable(); // Nome do destinatário (pode ser diferente do user)
            $table->string('address', 255);
            $table->string('number', 20);
            $table->string('neighborhood', 100);
            $table->string('complement', 100)->nullable();
            $table->string('city', 100);
            $table->string('state', 2);
            $table->string('zip_code', 15);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};

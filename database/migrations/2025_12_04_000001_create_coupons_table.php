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
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->enum('type', ['fixed', 'percentage']); // fixo ou porcentagem
            $table->decimal('value', 10, 2); // valor do desconto
            $table->integer('max_uses')->nullable(); // limite global de uso (null = ilimitado)
            $table->integer('max_uses_per_user')->default(1); // quantas vezes cada usuário pode usar
            $table->integer('current_uses')->default(0); // contador de usos totais
            $table->date('valid_from')->nullable(); // data de início da validade
            $table->date('valid_until')->nullable(); // data de fim da validade
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dropar e recriar a tabela com id auto-increment correto
        Schema::dropIfExists('delivery_settings');

        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_delivery_enabled')->default(true)->comment('Se a cobrança de entrega está ativa');
            $table->decimal('delivery_fee', 10, 2)->default(0)->comment('Valor da taxa de entrega');
            $table->text('description')->nullable()->comment('Descrição sobre a entrega/excursão');
            $table->timestamps();
        });

        // Inserir configuração padrão
        DB::table('delivery_settings')->insert([
            'is_delivery_enabled' => true,
            'delivery_fee' => 10.00,
            'description' => 'Configuração padrão de entrega',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_settings');
    }
};

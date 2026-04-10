<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->decimal('minimum_order_value', 10, 2)->default(0)->after('delivery_fee')
                ->comment('Valor mínimo do carrinho para fechar pedido. 0 = sem mínimo');
            $table->text('minimum_order_message')->nullable()->after('minimum_order_value')
                ->comment('Mensagem exibida quando o valor mínimo não é atingido');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn(['minimum_order_value', 'minimum_order_message']);
        });
    }
};

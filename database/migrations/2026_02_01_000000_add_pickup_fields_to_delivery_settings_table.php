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
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->boolean('is_pickup_enabled')->default(false)->comment('Se a opção de retirada no local está ativa')->after('is_delivery_enabled');
            $table->text('pickup_address')->nullable()->comment('Endereço completo para retirada')->after('is_pickup_enabled');
            $table->text('pickup_instructions')->nullable()->comment('Instruções adicionais (horário, como chegar, etc.)')->after('pickup_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn(['is_pickup_enabled', 'pickup_address', 'pickup_instructions']);
        });
    }
};

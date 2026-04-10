<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->string('cutoff_day', 20)->default('friday')->comment('Dia da semana limite (monday, tuesday, etc)');
            $table->time('cutoff_time')->default('11:00:00')->comment('Horário limite para entregas');
            $table->text('next_delivery_message')->nullable()->comment('Mensagem quando passar do prazo');
        });

        // Atualizar o registro existente com valores padrão
        DB::table('delivery_settings')->where('id', 1)->update([
            'cutoff_day' => 'friday',
            'cutoff_time' => '11:00:00',
            'next_delivery_message' => 'Prazo encerrado! As entregas serão realizadas apenas na próxima semana.',
        ]);
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn(['cutoff_day', 'cutoff_time', 'next_delivery_message']);
        });
    }
};

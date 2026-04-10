<?php

namespace Database\Seeders;

use App\Models\DeliverySetting;
use Illuminate\Database\Seeder;

class DeliverySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cria configuração padrão de entrega
        DeliverySetting::updateOrCreateSettings([
            'is_delivery_enabled' => true,
            'delivery_fee' => 10.00,
            'description' => 'Configuração padrão de entrega',
            'cutoff_day' => 'friday',
            'cutoff_time' => '11:00',
            'next_delivery_message' => 'Prazo encerrado! As entregas serão realizadas apenas na próxima semana.',
        ]);
    }
}

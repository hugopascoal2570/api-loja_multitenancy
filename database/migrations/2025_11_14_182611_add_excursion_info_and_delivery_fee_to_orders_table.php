<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('excursion_info')->nullable()->comment('Informações da excursão para entrega');
            $table->decimal('delivery_fee', 10, 2)->default(0)->comment('Taxa de entrega cobrada');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['excursion_info', 'delivery_fee']);
        });
    }
};

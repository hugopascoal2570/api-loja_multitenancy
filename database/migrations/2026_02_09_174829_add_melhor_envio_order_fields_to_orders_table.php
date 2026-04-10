<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('melhor_envio_order_id')->nullable()->after('shipping_status');
            $table->string('melhor_envio_protocol')->nullable()->after('melhor_envio_order_id');
            $table->string('melhor_envio_label_url')->nullable()->after('melhor_envio_protocol');
            $table->timestamp('melhor_envio_paid_at')->nullable()->after('melhor_envio_label_url');
            $table->timestamp('melhor_envio_generated_at')->nullable()->after('melhor_envio_paid_at');
            $table->timestamp('melhor_envio_posted_at')->nullable()->after('melhor_envio_generated_at');
            $table->timestamp('melhor_envio_delivered_at')->nullable()->after('melhor_envio_posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'melhor_envio_order_id',
                'melhor_envio_protocol',
                'melhor_envio_label_url',
                'melhor_envio_paid_at',
                'melhor_envio_generated_at',
                'melhor_envio_posted_at',
                'melhor_envio_delivered_at',
            ]);
        });
    }
};

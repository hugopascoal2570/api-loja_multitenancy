<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('shipping_service_id')->nullable()->after('delivery_method');
            $table->string('shipping_service_name')->nullable()->after('shipping_service_id');
            $table->unsignedInteger('shipping_estimated_days')->nullable()->after('shipping_service_name');
            $table->string('tracking_code')->nullable()->after('shipping_estimated_days');
            $table->string('shipping_status')->nullable()->default('pending')->after('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_service_id',
                'shipping_service_name',
                'shipping_estimated_days',
                'tracking_code',
                'shipping_status',
            ]);
        });
    }
};

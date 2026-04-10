<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_address')->nullable()->after('delivery_method');
            $table->string('shipping_number', 20)->nullable()->after('shipping_address');
            $table->string('shipping_neighborhood', 100)->nullable()->after('shipping_number');
            $table->string('shipping_complement', 100)->nullable()->after('shipping_neighborhood');
            $table->string('shipping_city', 100)->nullable()->after('shipping_complement');
            $table->string('shipping_state', 2)->nullable()->after('shipping_city');
            $table->string('shipping_zip_code', 15)->nullable()->after('shipping_state');
            $table->string('shipping_recipient_name')->nullable()->after('shipping_zip_code');
            $table->string('shipping_phone', 20)->nullable()->after('shipping_recipient_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_address',
                'shipping_number',
                'shipping_neighborhood',
                'shipping_complement',
                'shipping_city',
                'shipping_state',
                'shipping_zip_code',
                'shipping_recipient_name',
                'shipping_phone',
            ]);
        });
    }
};

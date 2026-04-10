<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->text('store_notice')->nullable()->after('is_dynamic_shipping_enabled');
            $table->boolean('is_store_open')->default(true)->after('store_notice');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn(['store_notice', 'is_store_open']);
        });
    }
};

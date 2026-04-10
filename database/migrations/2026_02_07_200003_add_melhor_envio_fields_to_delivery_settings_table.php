<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->boolean('is_dynamic_shipping_enabled')->default(false)->after('is_pickup_enabled');
            $table->string('origin_zip_code')->nullable()->after('is_dynamic_shipping_enabled');
            $table->decimal('default_weight', 8, 3)->default(0.300)->after('origin_zip_code'); // kg
            $table->decimal('default_width', 8, 1)->default(20.0)->after('default_weight');   // cm
            $table->decimal('default_height', 8, 1)->default(10.0)->after('default_width');   // cm
            $table->decimal('default_length', 8, 1)->default(30.0)->after('default_height');  // cm
        });
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_dynamic_shipping_enabled',
                'origin_zip_code',
                'default_weight',
                'default_width',
                'default_height',
                'default_length',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('color')->nullable()->after('type');
            $table->string('size')->nullable()->after('color');
        });

        // Preencher dados históricos a partir das variantes que ainda existem
        DB::statement("
            UPDATE order_items oi
            INNER JOIN product_variants pv ON oi.variant_id = pv.id
            SET oi.color = pv.color, oi.size = pv.size
            WHERE oi.variant_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['color', 'size']);
        });
    }
};

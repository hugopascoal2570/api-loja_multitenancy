<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->integer('quantity_rolls')->default(1)->after('color');
            $table->decimal('price_per_meter', 10, 2)->nullable()->after('price_per_roll');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropColumn(['quantity_rolls', 'price_per_meter']);
        });
    }
};

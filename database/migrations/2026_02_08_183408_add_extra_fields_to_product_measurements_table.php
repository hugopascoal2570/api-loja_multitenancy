<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->decimal('waistband', 6, 1)->nullable()->after('hip');     // Cós (cm)
            $table->decimal('rise', 6, 1)->nullable()->after('waistband');    // Gancho (cm)
            $table->decimal('inseam', 6, 1)->nullable()->after('rise');       // Entrepernas (cm)
            $table->decimal('thigh', 6, 1)->nullable()->after('inseam');      // Coxa (cm)
        });

        // Imagem do guia de medidas (uma por produto)
        Schema::table('products', function (Blueprint $table) {
            $table->string('measurement_image')->nullable()->after('length');
        });
    }

    public function down(): void
    {
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->dropColumn(['waistband', 'rise', 'inseam', 'thigh']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('measurement_image');
        });
    }
};

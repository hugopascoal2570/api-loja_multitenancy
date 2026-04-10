<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 8, 3)->nullable()->after('active'); // kg
            $table->decimal('width', 8, 1)->nullable()->after('weight');  // cm
            $table->decimal('height', 8, 1)->nullable()->after('width'); // cm
            $table->decimal('length', 8, 1)->nullable()->after('height'); // cm
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight', 'width', 'height', 'length']);
        });
    }
};

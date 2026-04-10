<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_kits', function (Blueprint $table) {
            $table->decimal('weight', 8, 3)->nullable()->after('is_active');
            $table->decimal('width', 8, 1)->nullable()->after('weight');
            $table->decimal('height', 8, 1)->nullable()->after('width');
            $table->decimal('length', 8, 1)->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('product_kits', function (Blueprint $table) {
            $table->dropColumn(['weight', 'width', 'height', 'length']);
        });
    }
};

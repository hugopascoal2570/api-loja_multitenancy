<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variants', 'barcode')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('barcode', 50)->nullable()->unique()->after('sku');
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};

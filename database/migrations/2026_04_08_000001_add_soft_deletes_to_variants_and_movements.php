<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove a FK de cascade hard-delete antes de adicionar soft delete
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_kits', function (Blueprint $table) {
            $table->boolean('is_redistributed')->default(false)->after('is_featured');
            $table->boolean('is_active')->default(true)->after('is_redistributed');
            $table->timestamp('redistributed_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_kits', function (Blueprint $table) {
            $table->dropColumn(['is_redistributed', 'is_active', 'redistributed_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mercado_livre_listings', function (Blueprint $table) {
            $table->string('ml_piece_chart_id')->nullable()->after('ml_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mercado_livre_listings', function (Blueprint $table) {
            $table->dropColumn('ml_piece_chart_id');
        });
    }
};

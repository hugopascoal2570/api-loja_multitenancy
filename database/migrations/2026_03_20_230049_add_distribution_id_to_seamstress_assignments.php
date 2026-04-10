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
        Schema::table('seamstress_assignments', function (Blueprint $table) {
            $table->foreignUuid('distribution_id')
                ->nullable()
                ->after('id')
                ->constrained('seamstress_distributions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seamstress_assignments', function (Blueprint $table) {
            $table->dropForeign(['distribution_id']);
            $table->dropColumn('distribution_id');
        });
    }
};

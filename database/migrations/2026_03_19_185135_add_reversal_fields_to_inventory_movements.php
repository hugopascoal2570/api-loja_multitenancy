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
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->uuid('reversal_of_id')->nullable()->after('user_id');
            $table->string('reversed_by')->nullable()->after('reversal_of_id');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');

            $table->foreign('reversal_of_id')
                ->references('id')
                ->on('inventory_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['reversal_of_id', 'reversed_by', 'reversed_at']);
        });
    }
};

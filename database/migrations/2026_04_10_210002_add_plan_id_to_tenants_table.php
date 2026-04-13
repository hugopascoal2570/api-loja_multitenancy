<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Remove the old string 'plan' column and replace with FK
            $table->dropColumn('plan');
            $table->foreignUuid('plan_id')->nullable()->after('is_active')
                ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
            $table->string('plan')->nullable()->comment('Para futura integração de cobrança: free, basic, pro, etc.');
        });
    }
};

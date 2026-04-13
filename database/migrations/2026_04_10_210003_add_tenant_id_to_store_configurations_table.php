<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_configurations', function (Blueprint $table) {
            // null = configuração global da plataforma (comportamento atual)
            // uuid = configuração específica de um tenant
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_configurations', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

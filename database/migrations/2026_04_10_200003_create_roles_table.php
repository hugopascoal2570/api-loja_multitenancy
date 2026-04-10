<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique()->comment('Ex: platform_super_admin, tenant_owner, tenant_manager, tenant_employee');
            $table->string('display_name')->comment('Nome legível. Ex: Super Admin, Dono da Loja');

            // platform = papel na plataforma inteira (super admin)
            // tenant   = papel dentro de uma empresa específica
            $table->enum('guard', ['platform', 'tenant'])->default('tenant');

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

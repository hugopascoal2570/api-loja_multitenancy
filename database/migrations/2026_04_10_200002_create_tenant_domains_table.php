<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain')->unique()->comment('Domínio completo. Ex: lojax.vendafacil.com.br');
            $table->boolean('is_primary')->default(false)->comment('Domínio principal do tenant');
            $table->timestamps();

            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};

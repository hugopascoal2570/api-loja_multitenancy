<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique()->comment('Identificador do subdomínio. Ex: lojax em lojax.vendafacil.com.br');
            $table->boolean('is_active')->default(true);
            $table->string('plan')->nullable()->comment('Para futura integração de cobrança: free, basic, pro, etc.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

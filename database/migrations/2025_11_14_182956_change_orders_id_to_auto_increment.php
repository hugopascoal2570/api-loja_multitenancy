<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Desabilitar verificação de chaves estrangeiras
        Schema::disableForeignKeyConstraints();

        // Remover a tabela orders e recriar com id auto-incremento
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        // Recriar tabela orders com id normal
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); // Auto-increment
            $table->string('order_number')->unique();
            $table->uuid('user_id');
            $table->uuid('cart_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0)->comment('Taxa de entrega');
            $table->text('excursion_info')->nullable()->comment('Informações da excursão');
            $table->string('payment_method');
            $table->string('payment_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'refunded'])->default('pending');
            $table->string('refund_id')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('set null');
        });

        // Recriar tabela order_items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // Mudou de uuid para bigint
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('kit_id')->nullable();
            $table->string('type')->comment('product, variant ou kit');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });

        // Reabilitar verificação de chaves estrangeiras
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Rollback: voltar para UUID
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->uuid('user_id');
            $table->uuid('cart_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method');
            $table->string('payment_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'refunded']);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }
};

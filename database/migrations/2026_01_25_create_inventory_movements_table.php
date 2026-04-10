<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_variant_id');
            $table->enum('type', ['in', 'out', 'adjustment'])->comment('in: entrada, out: saída, adjustment: ajuste');
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->enum('reason', ['sale', 'cancellation', 'refund', 'manual_add', 'manual_remove', 'manual_set'])->nullable();
            $table->unsignedBigInteger('related_order_id')->nullable()->comment('ID do pedido relacionado à movimentação');
            $table->text('notes')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');

            $table->foreign('related_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            $table->index('product_variant_id');
            $table->index('related_order_id');
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};

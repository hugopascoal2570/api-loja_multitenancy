<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_livre_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('ml_item_id')->unique();
            $table->enum('status', ['active', 'paused', 'closed', 'pending', 'error'])->default('pending');
            $table->string('ml_category_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('ml_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_livre_listings');
    }
};

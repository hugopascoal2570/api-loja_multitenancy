<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Mudar status para VARCHAR (aceita qualquer status do MP)
        DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'pending'");

        // 2) Adicionar campos de reembolso/cancelamento
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_id')->nullable()->after('payment_id');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_id');
            $table->timestamp('canceled_at')->nullable()->after('refund_amount');
            $table->text('cancel_reason')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_id', 'refund_amount', 'canceled_at', 'cancel_reason']);
        });

        // Se quiser reverter, volte para ENUM básico (opcional)
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending'");
    }
};

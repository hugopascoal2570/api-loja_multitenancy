<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove pedidos sem user_id
        DB::table('orders')->whereNull('user_id')->delete();

        // Verifica se colunas de guest existem
        $hasGuestColumns = Schema::hasColumns('orders', [
            'guest_name', 'guest_email', 'guest_whatsapp'
        ]);

        Schema::table('orders', function (Blueprint $table) use ($hasGuestColumns) {
            // Remove colunas guest se existirem
            if ($hasGuestColumns) {
                $table->dropColumn([
                    'guest_name',
                    'guest_email',
                    'guest_whatsapp'
                ]);
            }
        });

        // Remove a FK existente (se existir) e recria
        // Pega o nome da constraint diretamente do banco
        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'orders'
             AND COLUMN_NAME = 'user_id'
             AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        if (!empty($foreignKeys)) {
            $fkName = $foreignKeys[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fkName}`");
        }

        // Altera a coluna para NOT NULL e recria a FK
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove FK existente
            $table->dropForeign(['user_id']);

            // Torna user_id opcional novamente
            $table->uuid('user_id')->nullable()->change();

            // Recria colunas guest
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_whatsapp')->nullable()->after('guest_email');

            // Recria FK
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_configurations', function (Blueprint $table) {
            $table->text('melhor_envio_phone')->nullable()->change();
            $table->text('melhor_envio_email')->nullable()->change();
            $table->text('melhor_envio_state_register')->nullable()->change();
            $table->text('melhor_envio_address')->nullable()->change();
            $table->text('melhor_envio_complement')->nullable()->change();
            $table->text('melhor_envio_number')->nullable()->change();
            $table->text('melhor_envio_district')->nullable()->change();
            $table->text('melhor_envio_city')->nullable()->change();
            $table->text('melhor_envio_state_abbr')->nullable()->change();
            $table->text('ml_redirect_uri')->nullable()->change();
            $table->text('telegram_chat_id')->nullable()->change();
            $table->text('admin_notification_email')->nullable()->change();
            $table->text('mail_mailer')->nullable()->change();
            $table->text('mail_host')->nullable()->change();
            $table->text('mail_encryption')->nullable()->change();
            $table->text('mail_from_address')->nullable()->change();
            $table->text('mail_from_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('store_configurations', function (Blueprint $table) {
            $table->string('melhor_envio_phone')->nullable()->change();
            $table->string('melhor_envio_email')->nullable()->change();
            $table->string('melhor_envio_state_register')->nullable()->change();
            $table->string('melhor_envio_address')->nullable()->change();
            $table->string('melhor_envio_complement')->nullable()->change();
            $table->string('melhor_envio_number')->nullable()->change();
            $table->string('melhor_envio_district')->nullable()->change();
            $table->string('melhor_envio_city')->nullable()->change();
            $table->string('melhor_envio_state_abbr', 2)->nullable()->change();
            $table->string('ml_redirect_uri')->nullable()->change();
            $table->string('telegram_chat_id')->nullable()->change();
            $table->string('admin_notification_email')->nullable()->change();
            $table->string('mail_mailer')->nullable()->change();
            $table->string('mail_host')->nullable()->change();
            $table->string('mail_encryption')->nullable()->change();
            $table->string('mail_from_address')->nullable()->change();
            $table->string('mail_from_name')->nullable()->change();
        });
    }
};

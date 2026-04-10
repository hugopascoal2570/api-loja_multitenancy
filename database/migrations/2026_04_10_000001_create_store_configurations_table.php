<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_configurations', function (Blueprint $table) {
            $table->id();

            // ── Melhor Envio ─────────────────────────────────────────────
            $table->text('melhor_envio_token')->nullable();           // encrypted
            $table->text('melhor_envio_app_secret')->nullable();      // encrypted
            $table->boolean('melhor_envio_sandbox')->default(false);
            $table->string('melhor_envio_phone')->nullable();
            $table->string('melhor_envio_email')->nullable();
            $table->text('melhor_envio_document')->nullable();        // encrypted (CPF/CNPJ)
            $table->text('melhor_envio_company_document')->nullable(); // encrypted
            $table->string('melhor_envio_state_register')->nullable();
            $table->string('melhor_envio_address')->nullable();
            $table->string('melhor_envio_complement')->nullable();
            $table->string('melhor_envio_number')->nullable();
            $table->string('melhor_envio_district')->nullable();
            $table->string('melhor_envio_city')->nullable();
            $table->string('melhor_envio_state_abbr', 2)->nullable();

            // ── Mercado Livre ────────────────────────────────────────────
            $table->text('ml_client_id')->nullable();                 // encrypted
            $table->text('ml_client_secret')->nullable();             // encrypted
            $table->string('ml_redirect_uri')->nullable();

            // ── Mercado Pago ─────────────────────────────────────────────
            $table->text('mp_access_token')->nullable();              // encrypted
            $table->text('mp_public_key')->nullable();                // encrypted
            $table->text('mp_webhook_secret')->nullable();            // encrypted
            $table->boolean('mp_enforce_signature')->default(true);

            // ── Telegram ─────────────────────────────────────────────────
            $table->text('telegram_bot_token')->nullable();           // encrypted
            $table->string('telegram_chat_id')->nullable();
            $table->string('admin_notification_email')->nullable();

            // ── Super Admins ──────────────────────────────────────────────
            $table->text('super_admin_emails')->nullable();           // encrypted JSON

            // ── SMTP ──────────────────────────────────────────────────────
            $table->string('mail_mailer')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->text('mail_username')->nullable();                // encrypted
            $table->text('mail_password')->nullable();                // encrypted
            $table->string('mail_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_configurations');
    }
};

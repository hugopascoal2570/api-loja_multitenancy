<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Melhor Envio
            'melhor_envio_token'            => $this->melhor_envio_token ? '••••••••' : null,
            'melhor_envio_app_secret'       => $this->melhor_envio_app_secret ? '••••••••' : null,
            'melhor_envio_sandbox'          => $this->melhor_envio_sandbox,
            'melhor_envio_phone'            => $this->melhor_envio_phone,
            'melhor_envio_email'            => $this->melhor_envio_email,
            'melhor_envio_document'         => $this->melhor_envio_document ? '••••••••' : null,
            'melhor_envio_company_document' => $this->melhor_envio_company_document ? '••••••••' : null,
            'melhor_envio_state_register'   => $this->melhor_envio_state_register,
            'melhor_envio_address'          => $this->melhor_envio_address,
            'melhor_envio_complement'       => $this->melhor_envio_complement,
            'melhor_envio_number'           => $this->melhor_envio_number,
            'melhor_envio_district'         => $this->melhor_envio_district,
            'melhor_envio_city'             => $this->melhor_envio_city,
            'melhor_envio_state_abbr'       => $this->melhor_envio_state_abbr,
            'melhor_envio_configured'       => (bool) $this->melhor_envio_token,

            // Mercado Livre
            'ml_client_id'                  => $this->ml_client_id ? '••••••••' : null,
            'ml_client_secret'              => $this->ml_client_secret ? '••••••••' : null,
            'ml_redirect_uri'               => $this->ml_redirect_uri,
            'ml_configured'                 => (bool) $this->ml_client_id,

            // Mercado Pago
            'mp_access_token'               => $this->mp_access_token ? '••••••••' : null,
            'mp_public_key'                 => $this->mp_public_key ? '••••••••' : null,
            'mp_webhook_secret'             => $this->mp_webhook_secret ? '••••••••' : null,
            'mp_enforce_signature'          => $this->mp_enforce_signature,
            'mp_configured'                 => (bool) $this->mp_access_token,

            // Telegram
            'telegram_bot_token'            => $this->telegram_bot_token ? '••••••••' : null,
            'telegram_chat_id'              => $this->telegram_chat_id,
            'admin_notification_email'      => $this->admin_notification_email,
            'telegram_configured'           => (bool) $this->telegram_bot_token,

            // Super Admins
            'super_admin_emails'            => $this->super_admin_emails ?? [],

            // SMTP
            'mail_mailer'                   => $this->mail_mailer,
            'mail_host'                     => $this->mail_host,
            'mail_port'                     => $this->mail_port,
            'mail_username'                 => $this->mail_username ? '••••••••' : null,
            'mail_password'                 => $this->mail_password ? '••••••••' : null,
            'mail_encryption'               => $this->mail_encryption,
            'mail_from_address'             => $this->mail_from_address,
            'mail_from_name'                => $this->mail_from_name,
            'smtp_configured'               => (bool) $this->mail_host,

            'updated_at'                    => $this->updated_at,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;

class StoreConfiguration extends Model
{
    protected $fillable = [
        'tenant_id',
        // Melhor Envio
        'melhor_envio_token',
        'melhor_envio_app_secret',
        'melhor_envio_sandbox',
        'melhor_envio_phone',
        'melhor_envio_email',
        'melhor_envio_document',
        'melhor_envio_company_document',
        'melhor_envio_state_register',
        'melhor_envio_address',
        'melhor_envio_complement',
        'melhor_envio_number',
        'melhor_envio_district',
        'melhor_envio_city',
        'melhor_envio_state_abbr',
        // Mercado Livre
        'ml_client_id',
        'ml_client_secret',
        'ml_redirect_uri',
        // Mercado Pago
        'mp_access_token',
        'mp_public_key',
        'mp_webhook_secret',
        'mp_enforce_signature',
        // Telegram
        'telegram_bot_token',
        'telegram_chat_id',
        'admin_notification_email',
        // Super Admins
        'super_admin_emails',
        // SMTP
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    protected $casts = [
        // Melhor Envio — todos criptografados
        'melhor_envio_token'           => 'encrypted',
        'melhor_envio_app_secret'      => 'encrypted',
        'melhor_envio_phone'           => 'encrypted',
        'melhor_envio_email'           => 'encrypted',
        'melhor_envio_document'        => 'encrypted',
        'melhor_envio_company_document'=> 'encrypted',
        'melhor_envio_state_register'  => 'encrypted',
        'melhor_envio_address'         => 'encrypted',
        'melhor_envio_complement'      => 'encrypted',
        'melhor_envio_number'          => 'encrypted',
        'melhor_envio_district'        => 'encrypted',
        'melhor_envio_city'            => 'encrypted',
        'melhor_envio_state_abbr'      => 'encrypted',
        // Mercado Livre
        'ml_client_id'                 => 'encrypted',
        'ml_client_secret'             => 'encrypted',
        'ml_redirect_uri'              => 'encrypted',
        // Mercado Pago
        'mp_access_token'              => 'encrypted',
        'mp_public_key'                => 'encrypted',
        'mp_webhook_secret'            => 'encrypted',
        // Telegram
        'telegram_bot_token'           => 'encrypted',
        'telegram_chat_id'             => 'encrypted',
        'admin_notification_email'     => 'encrypted',
        // Super Admins
        'super_admin_emails'           => 'encrypted:array',
        // SMTP
        'mail_mailer'                  => 'encrypted',
        'mail_host'                    => 'encrypted',
        'mail_username'                => 'encrypted',
        'mail_password'                => 'encrypted',
        'mail_encryption'              => 'encrypted',
        'mail_from_address'            => 'encrypted',
        'mail_from_name'               => 'encrypted',
        // Booleans (não precisam de criptografia)
        'melhor_envio_sandbox'         => 'boolean',
        'mp_enforce_signature'         => 'boolean',
        // Numérico
        'mail_port'                    => 'integer',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Retorna a configuração do contexto atual:
     * - Dentro de um tenant: configuração específica do tenant (ou cria uma vazia)
     * - Fora de tenant (plataforma): configuração global (tenant_id = null)
     */
    public static function current(): static
    {
        if (app()->bound('tenant')) {
            $tenantId = app('tenant')->id;
            return static::firstOrCreate(['tenant_id' => $tenantId]);
        }

        return static::firstOrCreate(['tenant_id' => null]);
    }

    /**
     * Retorna (ou cria) a configuração de um tenant específico pelo ID.
     */
    public static function forTenant(string $tenantId): static
    {
        return static::firstOrCreate(['tenant_id' => $tenantId]);
    }

    /**
     * Retorna a configuração global da plataforma (tenant_id = null).
     */
    public static function platform(): static
    {
        return static::firstOrCreate(['tenant_id' => null]);
    }
}

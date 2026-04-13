<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreConfigurationResource;
use App\Models\StoreConfiguration;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantConfigurationController extends Controller
{
    /**
     * GET /api/superadmin/tenants/{tenant}/configuration
     * Retorna a configuração atual do tenant (cria vazia se não existir).
     */
    public function show(Tenant $tenant): StoreConfigurationResource
    {
        $config = StoreConfiguration::forTenant($tenant->id);
        return new StoreConfigurationResource($config);
    }

    /**
     * PUT /api/superadmin/tenants/{tenant}/configuration
     * Cria ou atualiza campos individuais da configuração do tenant.
     * Apenas os campos enviados são alterados (patch semântico).
     * O campo super_admin_emails é ignorado — pertence à config global da plataforma.
     */
    public function update(Request $request, Tenant $tenant): StoreConfigurationResource
    {
        $data = $request->validate([
            // Melhor Envio
            'melhor_envio_token'            => 'nullable|string',
            'melhor_envio_app_secret'       => 'nullable|string',
            'melhor_envio_sandbox'          => 'nullable|boolean',
            'melhor_envio_phone'            => 'nullable|string|max:20',
            'melhor_envio_email'            => 'nullable|email|max:255',
            'melhor_envio_document'         => 'nullable|string|max:20',
            'melhor_envio_company_document' => 'nullable|string|max:20',
            'melhor_envio_state_register'   => 'nullable|string|max:50',
            'melhor_envio_address'          => 'nullable|string|max:255',
            'melhor_envio_complement'       => 'nullable|string|max:100',
            'melhor_envio_number'           => 'nullable|string|max:10',
            'melhor_envio_district'         => 'nullable|string|max:100',
            'melhor_envio_city'             => 'nullable|string|max:100',
            'melhor_envio_state_abbr'       => 'nullable|string|size:2',

            // Mercado Livre
            'ml_client_id'                  => 'nullable|string',
            'ml_client_secret'              => 'nullable|string',
            'ml_redirect_uri'               => 'nullable|url|max:255',

            // Mercado Pago
            'mp_access_token'               => 'nullable|string',
            'mp_public_key'                 => 'nullable|string',
            'mp_webhook_secret'             => 'nullable|string',
            'mp_enforce_signature'          => 'nullable|boolean',

            // Telegram / Notificações
            'telegram_bot_token'            => 'nullable|string',
            'telegram_chat_id'              => 'nullable|string|max:50',
            'admin_notification_email'      => 'nullable|email|max:255',

            // SMTP
            'mail_mailer'                   => 'nullable|string|max:50',
            'mail_host'                     => 'nullable|string|max:255',
            'mail_port'                     => 'nullable|integer|min:1|max:65535',
            'mail_username'                 => 'nullable|string|max:255',
            'mail_password'                 => 'nullable|string',
            'mail_encryption'               => 'nullable|in:tls,ssl,starttls',
            'mail_from_address'             => 'nullable|email|max:255',
            'mail_from_name'                => 'nullable|string|max:100',
        ]);

        $config = StoreConfiguration::forTenant($tenant->id);
        $config->update($data);

        return new StoreConfigurationResource($config->fresh());
    }

    /**
     * DELETE /api/superadmin/tenants/{tenant}/configuration
     * Remove (zera) toda a configuração do tenant.
     * O registro é deletado — na próxima leitura será recriado vazio.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        StoreConfiguration::where('tenant_id', $tenant->id)->delete();

        return response()->json(['message' => 'Configuração do tenant removida com sucesso.']);
    }
}

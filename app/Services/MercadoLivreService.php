<?php

namespace App\Services;

use App\Models\MercadoLivreToken;
use App\Models\StoreConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoLivreService
{
    private const BASE_URL = 'https://api.mercadolibre.com';
    private const AUTH_URL = 'https://auth.mercadolivre.com.br/authorization';
    private const TOKEN_URL = 'https://api.mercadolibre.com/oauth/token';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $config = StoreConfiguration::current();
        $this->clientId     = $config->ml_client_id     ?? env('ML_CLIENT_ID');
        $this->clientSecret = $config->ml_client_secret ?? env('ML_CLIENT_SECRET');
        $this->redirectUri  = $config->ml_redirect_uri  ?? env('ML_REDIRECT_URI');
    }

    // -------------------------------------------------------------------------
    // OAuth2
    // -------------------------------------------------------------------------

    public function getAuthorizationUrl(): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
        ]);
    }

    public function exchangeCodeForToken(string $code): MercadoLivreToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
        ]);

        if ($response->failed()) {
            Log::error('ML OAuth exchange failed', ['body' => $response->body()]);
            throw new RuntimeException('Falha ao obter token do Mercado Livre.');
        }

        $data = $response->json();

        return MercadoLivreToken::updateOrCreate(
            ['seller_id' => (string) $data['user_id']],
            [
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => now()->addSeconds($data['expires_in']),
            ]
        );
    }

    public function refreshToken(MercadoLivreToken $token): MercadoLivreToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $token->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('ML token refresh failed', ['seller_id' => $token->seller_id]);
            throw new RuntimeException('Falha ao renovar token do Mercado Livre.');
        }

        $data = $response->json();

        $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => now()->addSeconds($data['expires_in']),
        ]);

        return $token->fresh();
    }

    // -------------------------------------------------------------------------
    // Token helper
    // -------------------------------------------------------------------------

    public function getValidToken(): MercadoLivreToken
    {
        $token = MercadoLivreToken::first();

        if (!$token) {
            throw new RuntimeException('Nenhuma conta do Mercado Livre conectada. Acesse /api/mercadolivre/auth/connect para autorizar.');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        return $token;
    }

    // -------------------------------------------------------------------------
    // API calls (base)
    // -------------------------------------------------------------------------

    public function get(string $path, array $query = []): array
    {
        $token    = $this->getValidToken();
        $response = Http::withToken($token->access_token)
            ->get(self::BASE_URL . $path, $query);

        if ($response->failed()) {
            Log::error('ML API GET failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Erro na API do Mercado Livre: ' . $response->status());
        }

        return $response->json();
    }

    public function post(string $path, array $data): array
    {
        $token    = $this->getValidToken();
        $response = Http::withToken($token->access_token)
            ->post(self::BASE_URL . $path, $data);

        if ($response->failed()) {
            $body = $response->json();
            Log::error('ML API POST failed', ['path' => $path, 'status' => $response->status(), 'body' => $body]);
            $mlMessage = $body['message'] ?? ($body['error'] ?? $response->body());
            throw new RuntimeException('Erro na API do Mercado Livre: ' . $mlMessage);
        }

        return $response->json();
    }

    public function put(string $path, array $data): array
    {
        $token    = $this->getValidToken();
        $response = Http::withToken($token->access_token)
            ->put(self::BASE_URL . $path, $data);

        if ($response->failed()) {
            $body = $response->json();
            Log::error('ML API PUT failed', ['path' => $path, 'status' => $response->status(), 'body' => $body]);
            $mlMessage = $body['message'] ?? ($body['error'] ?? $response->body());
            throw new RuntimeException('Erro na API do Mercado Livre: ' . $mlMessage);
        }

        return $response->json();
    }

    // -------------------------------------------------------------------------
    // Seller info
    // -------------------------------------------------------------------------

    public function getMe(): array
    {
        return $this->get('/users/me');
    }

    public function isConnected(): bool
    {
        return MercadoLivreToken::exists();
    }

    // -------------------------------------------------------------------------
    // Shipments / Etiqueta
    // -------------------------------------------------------------------------

    /**
     * Retorna os dados de um envio (shipment).
     * Campos importantes: status, substatus, logistic.mode, logistic.type
     */
    public function getShipment(string $shipmentId): array
    {
        return $this->get("/shipments/{$shipmentId}");
    }

    /**
     * Baixa a etiqueta de envio do ML.
     * Endpoint correto: GET /shipment_labels?shipment_ids={id}&response_type=pdf|zpl2
     *
     * O shipment precisa ter substatus = "ready_to_print" para a etiqueta estar disponível.
     * A DC-e (Declaração de Conteúdo) já vem embutida na etiqueta gerada pelo ML.
     *
     * @return array{content: string, content_type: string}
     */
    public function getShipmentLabel(string $shipmentId, string $responseType = 'pdf'): array
    {
        $token    = $this->getValidToken();
        $response = Http::withToken($token->access_token)
            ->get(self::BASE_URL . '/shipment_labels', [
                'shipment_ids'  => $shipmentId,
                'response_type' => $responseType,
            ]);

        if ($response->failed()) {
            Log::error('ML label fetch failed', [
                'shipment_id' => $shipmentId,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new RuntimeException('Erro ao buscar etiqueta no Mercado Livre: ' . $response->status());
        }

        return [
            'content'      => $response->body(),
            'content_type' => $response->header('Content-Type') ?? 'application/pdf',
        ];
    }
}

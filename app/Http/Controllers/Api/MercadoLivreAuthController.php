<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMercadoLivreOrderJob;
use App\Services\MercadoLivreService;
use Illuminate\Http\Request;

class MercadoLivreAuthController extends Controller
{
    public function __construct(private MercadoLivreService $ml) {}

    /**
     * Redireciona o admin para a tela de autorização do ML.
     * GET /api/mercadolivre/auth/connect
     */
    public function connect()
    {
        return redirect($this->ml->getAuthorizationUrl());
    }

    /**
     * ML redireciona aqui após o admin autorizar o app.
     * GET /api/mercadolivre/auth/callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return response()->json(['message' => 'Código de autorização não recebido.'], 400);
        }

        $token = $this->ml->exchangeCodeForToken($code);

        return response()->json([
            'message'   => 'Mercado Livre conectado com sucesso!',
            'seller_id' => $token->seller_id,
            'expires_at' => $token->expires_at,
        ]);
    }

    /**
     * Retorna o status da conexão.
     * GET /api/mercadolivre/auth/status
     */
    public function status()
    {
        if (!$this->ml->isConnected()) {
            return response()->json(['connected' => false]);
        }

        $me = $this->ml->getMe();

        return response()->json([
            'connected' => true,
            'seller_id' => $me['id'] ?? null,
            'nickname'  => $me['nickname'] ?? null,
            'email'     => $me['email'] ?? null,
        ]);
    }

    /**
     * Remove o token salvo (desconectar).
     * DELETE /api/mercadolivre/auth/disconnect
     */
    public function disconnect()
    {
        \App\Models\MercadoLivreToken::truncate();

        return response()->json(['message' => 'Conta do Mercado Livre desconectada.']);
    }

    /**
     * Recebe notificações do Mercado Livre.
     * POST /api/mercadolivre/webhook
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        \Illuminate\Support\Facades\Log::info('ML webhook recebido', [
            'topic'    => $payload['topic'] ?? null,
            'resource' => $payload['resource'] ?? null,
        ]);

        // Notificações de pedido: topic = "orders_v2"
        $topic    = $payload['topic'] ?? '';
        $resource = $payload['resource'] ?? ''; // ex: "/orders/12345678"

        if ($topic === 'orders_v2' && $resource) {
            $mlOrderId = basename($resource); // extrai o ID do caminho "/orders/123"

            if (is_numeric($mlOrderId)) {
                ProcessMercadoLivreOrderJob::dispatch($mlOrderId)
                    ->delay(now()->addSeconds(3));
            }
        }

        // ML exige resposta 200 imediata
        return response()->json(['status' => 'ok']);
    }
}

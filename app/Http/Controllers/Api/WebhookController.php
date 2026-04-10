<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderNotificationsJob;
use App\Models\StoreConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\MercadoPagoService;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1) Log de metadados (sem headers sensíveis como x-signature)
        Log::info('MP Webhook hit', [
            'has_signature' => $request->hasHeader('x-signature'),
            'content_type'  => $request->header('Content-Type'),
            'body_length'   => strlen($request->getContent()),
        ]);

        // 2) Validação da assinatura (x-signature)
        $signature    = $request->header('x-signature');
        $requestId    = $request->header('x-request-id');
        $payload      = $request->json()->all();
        $id           = data_get($payload, 'data.id');

        $storeConfig = StoreConfiguration::current();
        $enforce = $storeConfig->mp_enforce_signature ?? config('services.mercadopago.enforce_signature', true);
        if ($enforce) {
            if (!$this->isValidSignature($signature, $requestId, $id)) {
                Log::warning('MP Webhook invalid signature', compact('signature','requestId','id','payload'));
                return response()->json(['status' => 'ok'], 200); // responde 200 mas não processa
            }
        }

        // 3) Responde 200 rápido e processa (poderia enfileirar um Job)
        try {
            $type  = data_get($payload, 'type');   // ex: "payment"
            $action= data_get($payload, 'action'); // ex: "payment.updated"

            if ($type === 'payment' && $id) {
                // Buscar o pagamento no MP e atualizar seu pedido
                $this->updateOrderStatusFromPayment($id);
            } else {
                Log::info('MP Webhook - tipo não tratado', compact('type','action','id'));
            }
        } catch (\Throwable $e) {
            Log::error('MP Webhook processing error', ['ex' => $e]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function isValidSignature(?string $signature, ?string $requestId, ?string $id): bool
    {
        try {
            if (!$signature || !$requestId || !$id) return false;

            // x-signature exemplo: "ts=1726700000, v1=abcdef123..."
            $parts = collect(explode(',', $signature))
                ->map(fn($p) => array_map('trim', explode('=', $p)))
                ->filter(fn($kv) => count($kv) === 2)
                ->pluck(1, 0);

            $ts   = $parts->get('ts');
            $v1   = $parts->get('v1');

            if (!$ts || !$v1) return false;

            $manifest = "id:{$id};request-id:{$requestId};ts:{$ts};";
            $config   = StoreConfiguration::current();
            $secret   = $config->mp_webhook_secret ?? config('services.mercadopago.webhook_secret');

            if (empty($secret)) {
                Log::critical('MERCADOPAGO_WEBHOOK_SECRET não configurado — webhook rejeitado');
                return false;
            }

            $calc = hash_hmac('sha256', $manifest, $secret);

            $ok = hash_equals($v1, $calc);
            if (!$ok) {
                Log::warning('MP signature mismatch', ['manifest' => $manifest, 'v1' => $v1, 'calc' => $calc]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::error('MP signature validation error', ['ex' => $e]);
            return false;
        }
    }

    private function updateOrderStatusFromPayment(string $paymentId): void
    {
        // Autentica SDK
        $cfg = StoreConfiguration::current();
        MercadoPagoConfig::setAccessToken($cfg->mp_access_token ?? config('services.mercadopago.access_token'));

        $client  = new PaymentClient();
        $payment = $client->get($paymentId); // busca status real no MP

        $mpStatus = $payment->status ?? null; // approved, pending, in_process, rejected, refunded, etc.

        if (!$mpStatus) {
            Log::warning('Pagamento sem status', ['payment_id' => $paymentId]);
            return;
        }

        // Mapeia o status do Mercado Pago para o status aceito pela tabela orders
        $service = new MercadoPagoService();
        $mappedStatus = $service->mapPaymentStatus($mpStatus);

        // IMPORTANTE: Busca o pedido com as relações necessárias para o Observer
        $order = Order::with(['items.variant'])
            ->where('payment_id', $paymentId)
            ->first();

        if (!$order) {
            Log::warning('Pedido não encontrado para payment_id', ['payment_id' => $paymentId]);
            return;
        }

        $previousStatus = $order->status;

        // Atualiza o status (isso vai disparar o Observer com as relações já carregadas)
        $order->status = $mappedStatus;
        $order->save();

        Log::info('Order updated by webhook', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_id' => $paymentId,
            'mp_status' => $mpStatus,
            'mapped_status' => $mappedStatus,
        ]);

        // Notificar admin quando pedido e aprovado (e nao era aprovado antes)
        if ($mappedStatus === 'approved' && $previousStatus !== 'approved') {
            SendOrderNotificationsJob::dispatch($order->id);
        }
    }
}

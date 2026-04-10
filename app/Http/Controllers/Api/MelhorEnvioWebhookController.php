<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MelhorEnvioWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.melhor_envio.app_secret');

        if (empty($secret)) {
            Log::critical('[ME Webhook] MELHOR_ENVIO_APP_SECRET não configurado — webhook rejeitado');
            return response()->json(['status' => 'error'], 403);
        }

        $signature = $request->header('X-ME-Signature');
        $expected  = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature ?? '')) {
            Log::warning('[ME Webhook] Assinatura inválida', ['ip' => $request->ip()]);
            return response()->json(['status' => 'error'], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? null;

        if (!$event || !$data) {
            Log::warning('[ME Webhook] Payload sem event ou data', $payload);
            return response()->json(['status' => 'ok'], 200);
        }

        try {
            $this->processEvent($event, $data);
        } catch (\Throwable $e) {
            Log::error('[ME Webhook] Erro ao processar', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function processEvent(string $event, array $data): void
    {
        $meOrderId = $data['id'] ?? null;

        if (!$meOrderId) {
            Log::warning('[ME Webhook] Evento sem ID', ['event' => $event]);
            return;
        }

        $order = Order::where('melhor_envio_order_id', $meOrderId)->first();

        if (!$order) {
            Log::info('[ME Webhook] Pedido não encontrado para ME order', [
                'melhor_envio_order_id' => $meOrderId,
                'event' => $event,
            ]);
            return;
        }

        $updates = [];

        // Mapeia evento para shipping_status
        $statusMap = [
            'order.created' => 'pending',
            'order.pending' => 'pending',
            'order.released' => 'paid',
            'order.generated' => 'generated',
            'order.posted' => 'posted',
            'order.received' => 'in_transit',
            'order.delivered' => 'delivered',
            'order.cancelled' => 'cancelled',
            'order.undelivered' => 'undelivered',
            'order.paused' => 'paused',
            'order.suspended' => 'suspended',
        ];

        if (isset($statusMap[$event])) {
            $updates['shipping_status'] = $statusMap[$event];
        }

        // Atualiza tracking code se disponível
        if (!empty($data['tracking'])) {
            $updates['tracking_code'] = $data['tracking'];
        }

        // Atualiza protocol
        if (!empty($data['protocol']) && empty($order->melhor_envio_protocol)) {
            $updates['melhor_envio_protocol'] = $data['protocol'];
        }

        // Timestamps
        if (!empty($data['paid_at']) && empty($order->melhor_envio_paid_at)) {
            $updates['melhor_envio_paid_at'] = $data['paid_at'];
        }

        if (!empty($data['generated_at']) && empty($order->melhor_envio_generated_at)) {
            $updates['melhor_envio_generated_at'] = $data['generated_at'];
        }

        if (!empty($data['posted_at']) && empty($order->melhor_envio_posted_at)) {
            $updates['melhor_envio_posted_at'] = $data['posted_at'];
        }

        if (!empty($data['delivered_at']) && empty($order->melhor_envio_delivered_at)) {
            $updates['melhor_envio_delivered_at'] = $data['delivered_at'];
        }

        if (!empty($updates)) {
            $order->update($updates);

            Log::info('[ME Webhook] Pedido atualizado', [
                'order_number' => $order->order_number,
                'event' => $event,
                'updates' => $updates,
            ]);
        }
    }
}

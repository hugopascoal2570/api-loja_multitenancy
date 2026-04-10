<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\MelhorEnvioService;
use Illuminate\Http\Request;

class ShippingManagementController extends Controller
{
    public function __construct(
        private MelhorEnvioService $melhorEnvio
    ) {}

    /**
     * Compra etiqueta (carrinho + checkout + gerar + imprimir)
     * POST /api/shipping-management/{orderId}/purchase-label
     */
    public function purchaseLabel(string $orderId)
    {
        $order = Order::with(['items.product', 'user'])->findOrFail($orderId);

        if ($order->delivery_method !== 'shipping') {
            return response()->json([
                'message' => 'Este pedido não utiliza envio por transportadora.',
            ], 422);
        }

        // Bloqueia reemissão apenas se a etiqueta foi efetivamente gerada (paga + gerada)
        if ($order->melhor_envio_order_id && $order->shipping_status === 'generated') {
            return response()->json([
                'message' => 'Etiqueta já foi gerada para este pedido.',
                'melhor_envio_order_id' => $order->melhor_envio_order_id,
                'label_url' => $order->melhor_envio_label_url,
            ], 422);
        }

        $result = $this->melhorEnvio->purchaseLabel($order);

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
            ], 400);
        }

        $order->refresh();

        return response()->json([
            'message' => 'Etiqueta gerada com sucesso.',
            'melhor_envio_order_id' => $result['melhor_envio_order_id'],
            'protocol' => $result['protocol'],
            'label_url' => $result['label_url'],
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Imprime etiqueta (retorna URL de impressão)
     * GET /api/shipping-management/{orderId}/print-label
     */
    public function printLabel(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if (empty($order->melhor_envio_order_id)) {
            return response()->json([
                'message' => 'Etiqueta ainda não foi gerada para este pedido.',
            ], 422);
        }

        // Se já tem URL salva, retorna direto
        if ($order->melhor_envio_label_url) {
            return response()->json([
                'label_url' => $order->melhor_envio_label_url,
            ]);
        }

        $result = $this->melhorEnvio->printLabel($order->melhor_envio_order_id);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 400);
        }

        if (!empty($result['url'])) {
            $order->update(['melhor_envio_label_url' => $result['url']]);
        }

        return response()->json([
            'label_url' => $result['url'] ?? null,
        ]);
    }

    /**
     * Rastreia envio de um pedido
     * GET /api/shipping-management/{orderId}/tracking
     */
    public function tracking(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if (empty($order->melhor_envio_order_id)) {
            return response()->json([
                'message' => 'Nenhum envio registrado para este pedido.',
            ], 422);
        }

        $result = $this->melhorEnvio->tracking([$order->melhor_envio_order_id]);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 400);
        }

        // Sincroniza dados no pedido
        $this->melhorEnvio->syncTracking($order);
        $order->refresh();

        $trackingData = $result[$order->melhor_envio_order_id] ?? null;

        return response()->json([
            'order_number' => $order->order_number,
            'tracking_code' => $order->tracking_code,
            'shipping_status' => $order->shipping_status,
            'shipping_service_name' => $order->shipping_service_name,
            'melhor_envio_order_id' => $order->melhor_envio_order_id,
            'melhor_envio_protocol' => $order->melhor_envio_protocol,
            'melhor_envio_posted_at' => $order->melhor_envio_posted_at?->toDateTimeString(),
            'melhor_envio_delivered_at' => $order->melhor_envio_delivered_at?->toDateTimeString(),
            'tracking_details' => $trackingData,
        ]);
    }

    /**
     * Cancela etiqueta
     * POST /api/shipping-management/{orderId}/cancel-label
     */
    public function cancelLabel(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if (empty($order->melhor_envio_order_id)) {
            return response()->json([
                'message' => 'Nenhuma etiqueta encontrada para este pedido.',
            ], 422);
        }

        $reasonId    = (int) request('reason_id', 2);
        $description = request('description', 'Cancelamento solicitado pela loja');

        $result = $this->melhorEnvio->cancelLabel($order->melhor_envio_order_id, $reasonId, $description);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 400);
        }

        $order->update([
            'melhor_envio_order_id' => null,
            'melhor_envio_protocol' => null,
            'melhor_envio_label_url' => null,
            'melhor_envio_paid_at' => null,
            'melhor_envio_generated_at' => null,
            'shipping_status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Etiqueta cancelada com sucesso.',
            'order' => new OrderResource($order->refresh()),
        ]);
    }

    /**
     * Gera etiqueta de logística reversa (devolução)
     * POST /api/shipping-management/{orderId}/reverse-label
     */
    public function reverseLabel(Request $request, string $orderId)
    {
        $request->validate([
            'service_id' => 'required|integer',
        ]);

        $order = Order::with(['items.product', 'user'])->findOrFail($orderId);

        if ($order->delivery_method !== 'shipping') {
            return response()->json([
                'message' => 'Este pedido não utiliza envio por transportadora.',
            ], 422);
        }

        $result = $this->melhorEnvio->purchaseReverseLabel($order, $request->service_id);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Etiqueta reversa gerada com sucesso.',
            'melhor_envio_order_id' => $result['melhor_envio_order_id'],
            'protocol' => $result['protocol'],
            'tracking' => $result['tracking'],
        ]);
    }
}

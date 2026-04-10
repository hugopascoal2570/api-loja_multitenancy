<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeclareMLShipmentContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 120; // 2 min entre tentativas

    public function __construct(public readonly string $orderId) {}

    public function handle(MercadoLivreService $mlService): void
    {
        $order = Order::with('items.variant.product')->find($this->orderId);

        if (!$order) {
            Log::warning('DeclareMLShipment: pedido não encontrado', ['order_id' => $this->orderId]);
            return;
        }

        if (!$order->ml_shipment_id) {
            Log::warning('DeclareMLShipment: pedido sem ml_shipment_id', ['order_id' => $this->orderId]);
            return;
        }

        // Idempotência: já declarado
        if ($order->ml_content_declared_at) {
            Log::info('DeclareMLShipment: conteúdo já declarado', [
                'order_id'    => $this->orderId,
                'declared_at' => $order->ml_content_declared_at,
            ]);
            return;
        }

        // Verifica se o shipment aceita declaração de conteúdo
        try {
            $shipment = $mlService->getShipment($order->ml_shipment_id);
        } catch (\Throwable $e) {
            Log::error('DeclareMLShipment: erro ao buscar shipment', [
                'order_id'    => $this->orderId,
                'shipment_id' => $order->ml_shipment_id,
                'error'       => $e->getMessage(),
            ]);
            $this->fail($e);
            return;
        }

        $logisticType = $shipment['logistic_type'] ?? null;
        $mode         = $shipment['mode'] ?? null;

        // Declaração de conteúdo é relevante para envios via Correios/ME2 (me2 = Mercado Envios 2)
        // Para "fulfillment" o ML já cuida disso; pulamos
        if ($logisticType === 'fulfillment') {
            Log::info('DeclareMLShipment: fulfillment — declaração não necessária', [
                'order_id'    => $this->orderId,
                'shipment_id' => $order->ml_shipment_id,
            ]);
            return;
        }

        $items = $this->buildContentItems($order);

        if (empty($items)) {
            Log::warning('DeclareMLShipment: nenhum item para declarar', ['order_id' => $this->orderId]);
            return;
        }

        try {
            $mlService->declareShipmentContent($order->ml_shipment_id, $items);

            $order->update(['ml_content_declared_at' => now()]);

            Log::info('DeclareMLShipment: conteúdo declarado com sucesso', [
                'order_id'    => $this->orderId,
                'shipment_id' => $order->ml_shipment_id,
                'items_count' => count($items),
            ]);
        } catch (\Throwable $e) {
            Log::error('DeclareMLShipment: erro ao declarar conteúdo', [
                'order_id'    => $this->orderId,
                'shipment_id' => $order->ml_shipment_id,
                'error'       => $e->getMessage(),
            ]);
            throw $e; // permite retry automático
        }
    }

    /**
     * Monta os itens para a declaração de conteúdo com base nos itens do pedido.
     */
    private function buildContentItems(Order $order): array
    {
        $items = [];

        foreach ($order->items as $item) {
            $product = $item->variant?->product ?? null;

            // Valor unitário declarado: usa ml_price se disponível, senão unit_price do pedido
            $declaredValue = (float) ($product?->ml_price ?? $item->unit_price);

            $entry = [
                'description'    => $item->variant?->product?->name ?? 'Produto',
                'quantity'       => (int) $item->quantity,
                'declared_value' => round($declaredValue * $item->quantity, 2),
            ];

            // Inclui dimensões se o produto tiver cadastrado
            if ($product && $product->weight) {
                $entry['dimensions'] = [
                    'weight' => (float) $product->weight,
                    'width'  => (float) ($product->width  ?? 15),
                    'height' => (float) ($product->height ?? 5),
                    'length' => (float) ($product->length ?? 20),
                ];
            }

            $items[] = $entry;
        }

        return $items;
    }
}

<?php

namespace App\Jobs;

use App\Models\MercadoLivreListing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\MercadoLivreService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessMercadoLivreOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(public readonly string $mlOrderId) {}

    // Mapeamento de status do ML para status interno
    private const STATUS_MAP = [
        'cancelled'          => 'cancelled',
        'partially_refunded' => 'refunded',
        'invalid'            => 'cancelled',
        'pending_cancel'     => 'cancellation_requested', // comprador solicitou cancelamento
    ];

    public function handle(MercadoLivreService $mlService, TelegramNotificationService $telegram): void
    {
        $mlOrder   = $mlService->get("/orders/{$this->mlOrderId}");
        $mlStatus  = $mlOrder['status'] ?? '';
        $existing  = Order::where('ml_order_id', $this->mlOrderId)->first();

        // ── Pedido JÁ EXISTE no sistema → sincroniza status se mudou ──────────
        if ($existing) {
            $this->syncStatus($existing, $mlStatus, $mlOrder);
            return;
        }

        // ── Pedido NOVO → só cria se foi pago ────────────────────────────────
        if ($mlStatus !== 'paid') {
            Log::info('ML Order: status não é paid, ignorando', [
                'ml_order_id' => $this->mlOrderId,
                'status'      => $mlStatus,
            ]);
            return;
        }

        $order = null;

        DB::transaction(function () use ($mlOrder, $telegram, &$order) {
            $order = $this->createOrder($mlOrder);

            foreach ($mlOrder['order_items'] as $mlItem) {
                $this->createOrderItem($order, $mlItem);
            }

            // Aprova o pedido → dispara OrderObserver → decrementa estoque automaticamente
            $order->update(['status' => 'approved']);

            $telegram->sendMercadoLivreOrder($order->fresh(), $mlOrder);

            Log::info('ML Order: processado com sucesso', [
                'ml_order_id'  => $this->mlOrderId,
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ]);
        });

        // Nota: a DC-e (Declaração de Conteúdo) vem embutida na etiqueta gerada pelo ML.
        // Não há endpoint separado de declaração — basta baixar a etiqueta quando
        // o shipment tiver substatus = "ready_to_print".
    }

    /**
     * Atualiza o status do pedido local quando o ML notifica uma mudança.
     */
    private function syncStatus(Order $order, string $mlStatus, array $mlOrder = []): void
    {
        // Mediação aberta = comprador abriu reclamação/solicitou cancelamento
        // O ML mantém status "paid" mas coloca o pagamento em "in_mediation"
        $hasMediation    = !empty($mlOrder['mediations']);
        $paymentInMediation = collect($mlOrder['payments'] ?? [])
            ->contains('status', 'in_mediation');

        if ($hasMediation || $paymentInMediation) {
            $newStatus = 'cancellation_requested';
        } else {
            $newStatus = self::STATUS_MAP[$mlStatus] ?? null;
        }

        if (!$newStatus) {
            Log::info('ML Order: status atualizado mas sem ação necessária', [
                'ml_order_id'    => $this->mlOrderId,
                'ml_status'      => $mlStatus,
                'current_status' => $order->status,
            ]);
            return;
        }

        if ($order->status === $newStatus) {
            Log::info('ML Order: status já sincronizado', [
                'ml_order_id' => $this->mlOrderId,
                'status'      => $newStatus,
            ]);
            return;
        }

        $order->update([
            'status'      => $newStatus,
            'canceled_at' => $newStatus === 'cancelled' ? now() : $order->canceled_at,
            'cancel_reason' => $newStatus === 'cancelled' ? 'Cancelado pelo Mercado Livre' : $order->cancel_reason,
        ]);

        Log::info('ML Order: status sincronizado', [
            'ml_order_id' => $this->mlOrderId,
            'order_id'    => $order->id,
            'old_status'  => $order->getOriginal('status'),
            'new_status'  => $newStatus,
        ]);
    }

    private function createOrder(array $mlOrder): Order
    {
        $buyer      = $mlOrder['buyer'] ?? [];
        $buyerName  = trim(($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? ''));
        $totalAmount = (float) ($mlOrder['total_amount'] ?? 0);
        $shipmentId  = isset($mlOrder['shipping']['id']) ? (string) $mlOrder['shipping']['id'] : null;

        return Order::create([
            'id'             => Str::uuid(),
            'order_number'   => 'ML-' . $this->mlOrderId,
            'ml_order_id'    => (string) $this->mlOrderId,
            'ml_shipment_id' => $shipmentId,
            'source'         => 'mercadolivre',
            'status'         => 'pending', // será atualizado para 'approved' após criar os itens
            'payment_method' => 'mercadolivre',
            'payment_id'     => 'ML-' . $this->mlOrderId,
            'total_amount'   => $totalAmount,
            'delivery_fee'   => 0,
            'customer_name'  => $buyerName ?: 'Comprador ML',
        ]);
    }

    private function createOrderItem(Order $order, array $mlItem): void
    {
        $sku         = $mlItem['item']['seller_custom_field'] ?? null;
        $mlItemId    = $mlItem['item']['id'] ?? null;
        $quantity    = (int) ($mlItem['quantity'] ?? 1);
        $unitPrice   = (float) ($mlItem['unit_price'] ?? 0);

        // Atributos de variação (COLOR, SIZE)
        $attributes = collect($mlItem['variation_attributes'] ?? [])
            ->pluck('value_name', 'id');
        $color = $attributes->get('COLOR');
        $size  = $attributes->get('SIZE');

        $variant   = null;
        $productId = null;

        // 1. Localiza o produto pelo ml_item_id da listagem (vínculo confiável)
        if ($mlItemId) {
            $listing   = MercadoLivreListing::where('ml_item_id', $mlItemId)->first();
            $productId = $listing?->product_id;
        }

        // 2. Localiza a variante pelo SKU dentro do produto correto
        //    (evita pegar variante de outro produto com SKU duplicado)
        if ($productId && $sku) {
            $variant = ProductVariant::where('sku', $sku)
                ->where('product_id', $productId)
                ->first();
        }

        // 3. Fallback: busca SKU globalmente se não encontrou pelo produto
        if (!$variant && $sku) {
            $variant   = ProductVariant::where('sku', $sku)->first();
            $productId = $productId ?? $variant?->product_id;
        }

        if (!$productId) {
            Log::warning('ML Order: item sem produto correspondente', [
                'ml_order_id' => $this->mlOrderId,
                'ml_item_id'  => $mlItemId,
                'sku'         => $sku,
            ]);
            return;
        }

        OrderItem::create([
            'order_id'    => $order->id,
            'product_id'  => $productId,
            'variant_id'  => $variant?->id,
            'type'        => 'product',
            'color'       => $color,
            'size'        => $size,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMercadoLivreOrderJob;
use App\Models\MercadoLivreListing;
use App\Models\Order;
use App\Models\Product;
use App\Services\MercadoLivreListingService;
use App\Services\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MercadoLivreController extends Controller
{
    public function __construct(
        private MercadoLivreListingService $listingService,
        private MercadoLivreService $mlService,
    ) {}

    /**
     * Lista todos os anúncios publicados no ML.
     * GET /api/mercadolivre/listings
     */
    public function index()
    {
        $listings = MercadoLivreListing::with('product')
            ->latest('synced_at')
            ->paginate(50);

        return response()->json($listings);
    }

    /**
     * Status do anúncio de um produto específico.
     * GET /api/mercadolivre/listings/{productId}
     */
    public function show(string $productId)
    {
        $listing = MercadoLivreListing::where('product_id', $productId)
            ->with('product')
            ->first();

        if (!$listing) {
            return response()->json([
                'listed'  => false,
                'message' => 'Produto ainda não publicado no Mercado Livre.',
            ]);
        }

        return response()->json(array_merge($listing->toArray(), ['listed' => true]));
    }

    /**
     * Publica (ou atualiza) um produto no ML.
     * POST /api/mercadolivre/listings/{productId}
     */
    public function publish(string $productId)
    {
        $product = Product::with(['variants', 'images'])->findOrFail($productId);

        try {
            $listing = $this->listingService->publishProduct($product);

            return response()->json([
                'message'    => 'Produto publicado no Mercado Livre.',
                'ml_item_id' => $listing->ml_item_id,
                'status'     => $listing->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Pausa o anúncio no ML.
     * PUT /api/mercadolivre/listings/{productId}/pause
     */
    public function pause(string $productId)
    {
        $listing = MercadoLivreListing::where('product_id', $productId)->firstOrFail();
        $this->listingService->pauseListing($listing);

        return response()->json(['message' => 'Anúncio pausado.', 'status' => 'paused']);
    }

    /**
     * Reativa o anúncio no ML.
     * PUT /api/mercadolivre/listings/{productId}/activate
     */
    public function activate(string $productId)
    {
        $listing = MercadoLivreListing::where('product_id', $productId)->firstOrFail();
        $this->listingService->activateListing($listing);

        return response()->json(['message' => 'Anúncio reativado.', 'status' => 'active']);
    }

    /**
     * Remove o anúncio do ML (fecha a publicação).
     * DELETE /api/mercadolivre/listings/{productId}
     */
    public function destroy(string $productId)
    {
        $listing = MercadoLivreListing::where('product_id', $productId)->firstOrFail();

        try {
            $this->mlService->put(
                "/items/{$listing->ml_item_id}",
                ['status' => 'closed']
            );
        } catch (\Exception $e) {
            // Ignora erro se o item já estiver fechado no ML
        }

        $listing->update(['status' => 'closed', 'synced_at' => now()]);

        return response()->json(['message' => 'Anúncio encerrado.']);
    }

    // -------------------------------------------------------------------------
    // Envio / Etiqueta
    // -------------------------------------------------------------------------


    /**
     * Baixa a etiqueta de envio do ML para um pedido.
     * GET /api/mercadolivre/orders/{orderId}/label
     *
     * Query param: ?format=zpl2 (padrão) | pdf | html
     */
    public function shipmentLabel(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->source !== 'mercadolivre') {
            return response()->json(['message' => 'Este pedido não é do Mercado Livre.'], 422);
        }

        if (!$order->ml_shipment_id) {
            return response()->json(['message' => 'Pedido sem ID de envio do Mercado Livre.'], 422);
        }

        try {
            $format = request()->query('format', 'zpl2');
            $label  = $this->mlService->getShipmentLabel($order->ml_shipment_id, $format);

            return response($label['content'], 200)
                ->header('Content-Type', $label['content_type'])
                ->header('Content-Disposition', "inline; filename=\"etiqueta-{$order->order_number}.{$this->labelExtension($format)}\"");
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * Sincroniza o status de um pedido ML com o status atual na API do ML.
     * Útil para pedidos que foram cancelados antes do fix do webhook.
     * POST /api/mercadolivre/orders/{orderId}/sync-status
     */
    public function syncOrderStatus(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->source !== 'mercadolivre' || !$order->ml_order_id) {
            return response()->json(['message' => 'Este pedido não é do Mercado Livre.'], 422);
        }

        ProcessMercadoLivreOrderJob::dispatchSync($order->ml_order_id);

        $order->refresh();

        return response()->json([
            'message' => 'Status sincronizado.',
            'status'  => $order->status,
        ]);
    }

    /**
     * Consulta o status do envio no ML e informa se a etiqueta está pronta.
     * A DC-e já vem embutida na etiqueta — não há passo separado de declaração.
     * GET /api/mercadolivre/orders/{orderId}/shipment-status
     */
    public function shipmentStatus(string $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->source !== 'mercadolivre') {
            return response()->json(['message' => 'Este pedido não é do Mercado Livre.'], 422);
        }

        if (!$order->ml_shipment_id) {
            return response()->json(['message' => 'Pedido sem ID de envio do Mercado Livre.'], 422);
        }

        try {
            $shipment = $this->mlService->getShipment($order->ml_shipment_id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $status    = $shipment['status'] ?? null;
        $substatus = $shipment['substatus'] ?? null;
        $ready     = $substatus === 'ready_to_print';

        return response()->json([
            'shipment_id'       => $order->ml_shipment_id,
            'status'            => $status,
            'substatus'         => $substatus,
            'label_ready'       => $ready,
            'label_message'     => $ready
                ? 'Etiqueta pronta para impressão. A DC-e já vem embutida.'
                : "Etiqueta ainda não disponível. Status: {$status} / {$substatus}",
        ]);
    }

    private function labelExtension(string $format): string
    {
        return match ($format) {
            'pdf'  => 'pdf',
            'zpl2' => 'zpl',
            default => 'html',
        };
    }
}

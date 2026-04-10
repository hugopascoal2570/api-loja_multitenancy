<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\MelhorEnvioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncShippingTracking extends Command
{
    protected $signature = 'shipping:sync-tracking';
    protected $description = 'Sincroniza o rastreamento de envios com o Melhor Envio';

    public function handle(MelhorEnvioService $melhorEnvio)
    {
        $orders = Order::whereNotNull('melhor_envio_order_id')
            ->whereNull('melhor_envio_delivered_at')
            ->whereIn('shipping_status', ['paid', 'generated', 'posted', 'in_transit'])
            ->get();

        if ($orders->isEmpty()) {
            Log::info('[SyncShippingTracking] Nenhum envio pendente para sincronizar.');
            $this->info('Nenhum envio pendente para sincronizar.');
            return;
        }

        $this->info("Sincronizando {$orders->count()} envio(s)...");
        Log::info("[SyncShippingTracking] Iniciando sincronização de {$orders->count()} envio(s).");

        $meOrderIds = $orders->pluck('melhor_envio_order_id')->toArray();

        // Consulta tracking em lote
        $trackingResult = $melhorEnvio->tracking($meOrderIds);

        if (isset($trackingResult['error'])) {
            Log::error('[SyncShippingTracking] Erro ao consultar tracking em lote: ' . $trackingResult['error']);
            $this->error('Erro ao consultar tracking: ' . $trackingResult['error']);
            return;
        }

        $updated = 0;
        foreach ($orders as $order) {
            $info = $trackingResult[$order->melhor_envio_order_id] ?? null;

            if (!$info) {
                continue;
            }

            $updates = [];

            if (!empty($info['tracking']) && $info['tracking'] !== $order->tracking_code) {
                $updates['tracking_code'] = $info['tracking'];
            }

            if (!empty($info['status']) && $info['status'] !== $order->shipping_status) {
                $updates['shipping_status'] = $info['status'];
            }

            if (!empty($info['posted_at']) && empty($order->melhor_envio_posted_at)) {
                $updates['melhor_envio_posted_at'] = $info['posted_at'];
            }

            if (!empty($info['delivered_at']) && empty($order->melhor_envio_delivered_at)) {
                $updates['melhor_envio_delivered_at'] = $info['delivered_at'];
            }

            if (!empty($updates)) {
                $order->update($updates);
                $updated++;
                Log::info("[SyncShippingTracking] Pedido {$order->order_number} atualizado.", $updates);
            }
        }

        $this->info("{$updated} envio(s) atualizado(s).");
        Log::info("[SyncShippingTracking] Sincronização concluída. {$updated} atualizado(s).");
    }
}

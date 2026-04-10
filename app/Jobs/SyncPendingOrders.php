<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Support\Facades\Log;

class SyncPendingOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $client = new PaymentClient();

        // pega pedidos pendentes com mais de 5 minutos
        $orders = Order::where('status', 'pending')
            ->whereNotNull('payment_id')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            try {
                $payment = $client->get($order->payment_id);
                $status  = $payment->status ?? null;

                if ($status && $status !== $order->status) {
                    $order->update(['status' => $status]);
                    Log::info("Reconciliação: order {$order->id} atualizado para {$status}");
                }
            } catch (\Throwable $e) {
                Log::error('Erro ao reconciliar pedido', [
                    'order_id' => $order->id,
                    'ex'       => $e->getMessage()
                ]);
            }
        }
    }
}

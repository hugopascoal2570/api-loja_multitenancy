<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Support\Facades\Log;

class SyncPendingOrders extends Command
{
    protected $signature = 'orders:sync-pending';
    protected $description = 'Verifica ordens pendentes e sincroniza status com Mercado Pago';

    public function handle()
    {
        Log::info('[SyncPendingOrders] Iniciando verificação de ordens pendentes...');

        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $client = new PaymentClient();

        // 1) ORDERS PENDENTES -> consulta no MP e, se aprovar, completa o cart
        $pendingOrders = Order::where('status', 'pending')
            ->with('cart')
            ->get();

        if ($pendingOrders->isEmpty()) {
            Log::info('[SyncPendingOrders] Nenhuma ordem pendente encontrada para sincronização com o MP.');
        } else {
            foreach ($pendingOrders as $order) {
                try {
                    $payment = $client->get($order->payment_id);
                    if (!$payment) {
                        Log::warning("[SyncPendingOrders] Pagamento não encontrado no MP para {$order->order_number}.");
                        continue;
                    }

                    $remote = (string) $payment->status;
                    $local  = (string) $order->status;

                    // Atualiza a order se mudou
                    if ($remote !== $local) {
                        $order->update(['status' => $remote]);
                        Log::info("[SyncPendingOrders] Ordem {$order->order_number} atualizada: {$local} -> {$remote}.");
                    } else {
                        Log::info("[SyncPendingOrders] Sem mudança: {$order->order_number} permanece como {$local}.");
                    }

                    // Se pago, completa o cart (idempotente)
                    if (in_array($remote, ['approved'], true)) {
                        if ($order->cart && $order->cart->status !== 'completed') {
                            $order->cart->update(['status' => 'completed']);
                            Log::info("[SyncPendingOrders] Carrinho {$order->cart->id} marcado como 'completed' (pedido: {$order->order_number}).");
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("[SyncPendingOrders] Erro ao sincronizar ordem {$order->order_number}: " . $e->getMessage());
                }
            }
        }

        // 2) ORDERS JÁ APROVADAS (via webhook) CUJO CART AINDA ESTÁ PENDENTE -> completa o cart
        $approvedWithPendingCart = Order::where('status', 'approved')
            ->whereHas('cart', function ($q) {
                $q->where('status', 'pending');
            })
            ->with('cart')
            ->get();

        if ($approvedWithPendingCart->isNotEmpty()) {
            foreach ($approvedWithPendingCart as $order) {
                try {
                    if ($order->cart && $order->cart->status !== 'completed') {
                        $order->cart->update(['status' => 'completed']);
                        Log::info("[SyncPendingOrders] (Catch-up) Carrinho {$order->cart->id} marcado como 'completed' para pedido {$order->order_number} já aprovado.");
                    }
                } catch (\Exception $e) {
                    Log::error("[SyncPendingOrders] Erro ao completar carrinho (catch-up) do pedido {$order->order_number}: " . $e->getMessage());
                }
            }
        } else {
            Log::info('[SyncPendingOrders] Nenhum carrinho pendente encontrado para pedidos já aprovados.');
        }

        Log::info('[SyncPendingOrders] Verificação finalizada.');
    }
}

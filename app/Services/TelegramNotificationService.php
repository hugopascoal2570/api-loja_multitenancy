<?php

namespace App\Services;

use App\Models\StoreConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    private ?string $botToken;
    private array $chatIds;

    public function __construct()
    {
        $config = StoreConfiguration::current();

        $this->botToken = $config->telegram_bot_token ?? config('services.telegram.bot_token');

        $raw = $config->telegram_chat_id ?? config('services.telegram.chat_id', '');
        $this->chatIds = array_filter(
            array_map('trim', explode(',', $raw ?? ''))
        );
    }

    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatIds);
    }

    public function sendMessage(string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::info('Telegram não configurado, pulando notificação.');
            return false;
        }

        $success = false;

        foreach ($this->chatIds as $chatId) {
            try {
                $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);

                if ($response->successful()) {
                    Log::info("Telegram: mensagem enviada para {$chatId}.");
                    $success = true;
                } else {
                    Log::warning("Telegram: falha ao enviar para {$chatId}.", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("Telegram: erro ao enviar para {$chatId}.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $success;
    }

    public function sendMercadoLivreOrder(
        \App\Models\Order $order,
        array $mlOrder
    ): bool {
        $buyerName = trim(
            ($mlOrder['buyer']['first_name'] ?? '') . ' ' . ($mlOrder['buyer']['last_name'] ?? '')
        ) ?: 'Comprador ML';
        $itemCount  = count($mlOrder['order_items'] ?? []);
        $totalFmt   = number_format((float) $order->total_amount, 2, ',', '.');

        $message = "🛒 <b>NOVO PEDIDO - MERCADO LIVRE!</b>\n\n"
            . "📋 Pedido: <b>#{$order->order_number}</b>\n"
            . "🆔 ML ID: #{$mlOrder['id']}\n"
            . "👤 Comprador: {$buyerName}\n"
            . "💰 Valor: R$ {$totalFmt}\n"
            . "📦 Itens: {$itemCount}\n\n"
            . "✅ Pago no Mercado Livre";

        return $this->sendMessage($message);
    }

    public function sendOrderApproved(
        string $orderNumber,
        string $customerName,
        string $totalAmount,
        string $paymentMethod,
        int $itemCount
    ): bool {
        $message = "🛒 <b>NOVO PEDIDO PAGO!</b>\n\n"
            . "📋 Pedido: <b>#{$orderNumber}</b>\n"
            . "👤 Cliente: {$customerName}\n"
            . "💰 Valor: R$ {$totalAmount}\n"
            . "💳 Pagamento: {$paymentMethod}\n"
            . "📦 Itens: {$itemCount}\n\n"
            . "✅ Pagamento confirmado pelo Mercado Pago";

        return $this->sendMessage($message);
    }
}

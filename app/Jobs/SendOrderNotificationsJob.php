<?php

namespace App\Jobs;

use App\Mail\OrderApprovedMail;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private string $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::with(['user', 'items.product'])->find($this->orderId);

        if (!$order) {
            Log::warning('SendOrderNotificationsJob: pedido nao encontrado', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        // Verificar se ja foi notificado (evita duplicatas)
        $alreadyNotified = AdminNotification::where('type', 'order_approved')
            ->whereJsonContains('data->order_id', $order->id)
            ->exists();

        if ($alreadyNotified) {
            Log::info('SendOrderNotificationsJob: notificacao ja enviada, pulando.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            return;
        }

        // 1. Salvar notificacao no banco (para polling do painel admin)
        $this->createAdminNotification($order);

        // 2. Enviar email para o admin
        $this->sendAdminEmail($order);

        // 3. Enviar Telegram
        $this->sendTelegram($order);
    }

    private function createAdminNotification(Order $order): void
    {
        try {
            AdminNotification::create([
                'type' => 'order_approved',
                'title' => "Novo pedido pago #{$order->order_number}",
                'body' => "Cliente {$order->user?->name} - R$ " . number_format($order->total_amount, 2, ',', '.'),
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user?->name . ' ' . $order->user?->last_name,
                    'total_amount' => $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'items_count' => $order->items->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao criar admin notification', ['error' => $e->getMessage()]);
        }
    }

    private function sendAdminEmail(Order $order): void
    {
        try {
            $adminEmails = config('services.notifications.admin_email');

            if (empty($adminEmails)) {
                Log::info('Email de admin nao configurado, pulando envio.');
                return;
            }

            // Suporta múltiplos emails separados por vírgula
            $emails = array_map('trim', explode(',', $adminEmails));
            $emails = array_filter($emails);

            Mail::to($emails)->send(new OrderApprovedMail($order));

            Log::info('Email de pedido aprovado enviado', [
                'order_number' => $order->order_number,
                'admin_emails' => $emails,
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar email de pedido aprovado', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendTelegram(Order $order): void
    {
        try {
            $telegram = new TelegramNotificationService();

            $telegram->sendOrderApproved(
                $order->order_number,
                ($order->user?->name ?? 'Cliente') . ' ' . ($order->user?->last_name ?? ''),
                number_format($order->total_amount, 2, ',', '.'),
                $order->payment_method === 'pix' ? 'PIX' : 'Cartao de Credito',
                $order->items->count()
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar Telegram de pedido aprovado', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendOrderNotificationsJob falhou', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}

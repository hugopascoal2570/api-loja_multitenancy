<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build()
    {
        return $this->subject("Seu pedido {$this->order->order_number} foi cancelado")
            ->markdown('emails.orders.cancelled', [
                'order' => $this->order,
            ]);
    }
}

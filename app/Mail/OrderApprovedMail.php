<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build()
    {
        return $this->subject("Novo pedido pago - #{$this->order->order_number}")
            ->markdown('emails.orders.approved', [
                'order' => $this->order,
            ]);
    }
}

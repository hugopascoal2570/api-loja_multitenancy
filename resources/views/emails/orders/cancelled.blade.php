@component('mail::message')
# Pedido cancelado

Olá {{ $order->user?->name ?? 'cliente' }},

Seu pedido **{{ $order->order_number }}** foi cancelado.

**Status atual:** {{ strtoupper($order->status) }}

@isset($order->refund_amount)
**Valor reembolsado:** R$ {{ number_format($order->refund_amount, 2, ',', '.') }}
@endisset

Se tiver qualquer dúvida, basta responder este e-mail.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent

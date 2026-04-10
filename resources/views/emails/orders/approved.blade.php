@component('mail::message')
# Novo Pedido Pago!

Um novo pedido foi confirmado pelo Mercado Pago.

**Pedido:** #{{ $order->order_number }}

**Cliente:** {{ $order->user?->name }} {{ $order->user?->last_name }}

**Email:** {{ $order->user?->email }}

**Telefone:** {{ $order->user?->phone ?? 'Não informado' }}

**Valor total:** R$ {{ number_format($order->total_amount, 2, ',', '.') }}

@if($order->discount_amount > 0)
**Desconto:** R$ {{ number_format($order->discount_amount, 2, ',', '.') }}
@endif

@if($order->delivery_fee > 0)
**Frete:** R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}
@endif

**Forma de pagamento:** {{ $order->payment_method === 'pix' ? 'PIX' : 'Cartao de Credito' }}

**Entrega:** {{ $order->delivery_method === 'pickup' ? 'Retirada no local' : 'Entrega' }}

---

### Itens do pedido

@foreach($order->items as $item)
- {{ $item->product?->name ?? 'Produto' }} | {{ $item->size }}/{{ $item->color }} | Qtd: {{ $item->quantity }} | R$ {{ number_format($item->total_price, 2, ',', '.') }}
@endforeach

---

Acesse o painel para mais detalhes.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent

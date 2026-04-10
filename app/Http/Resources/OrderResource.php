<?php

namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'total_amount' => (float) $this->total_amount,
            'delivery_fee' => (float) $this->delivery_fee,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'subtotal' => (float) ($this->total_amount - $this->delivery_fee),
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel(),
            'payment_id' => $this->payment_id,
            'delivery_method' => $this->delivery_method,
            'delivery_method_label' => $this->source === 'counter' ? 'Venda de Balcão' : match($this->delivery_method) {
                'pickup' => 'Retirada',
                'excursion' => 'Excursão',
                'shipping' => 'Transportadora',
                default => $this->delivery_method,
            },
            'shipping_service_name' => $this->shipping_service_name,
            'shipping_estimated_days' => $this->shipping_estimated_days,
            'tracking_code' => $this->tracking_code,
            'shipping_status' => $this->shipping_status,
            'shipping_status_label' => $this->getShippingStatusLabel(),
            'melhor_envio_order_id' => $this->melhor_envio_order_id,
            'melhor_envio_protocol' => $this->melhor_envio_protocol,
            'melhor_envio_label_url' => $this->melhor_envio_label_url,
            'melhor_envio_paid_at' => $this->melhor_envio_paid_at?->toDateTimeString(),
            'melhor_envio_generated_at' => $this->melhor_envio_generated_at?->toDateTimeString(),
            'melhor_envio_posted_at' => $this->melhor_envio_posted_at?->toDateTimeString(),
            'melhor_envio_delivered_at' => $this->melhor_envio_delivered_at?->toDateTimeString(),
            'excursion_info' => $this->excursion_info,
            'shipping_address' => $this->shipping_address ? [
                'recipient_name' => $this->shipping_recipient_name,
                'address' => $this->shipping_address,
                'number' => $this->shipping_number,
                'neighborhood' => $this->shipping_neighborhood,
                'complement' => $this->shipping_complement,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'zip_code' => $this->shipping_zip_code,
                'phone' => $this->shipping_phone,
            ] : null,
            'items_count' => $this->items ? $this->items->count() : 0,
            'coupon' => $this->coupon_code ? [
                'code' => $this->coupon_code,
                'discount_amount' => (float) ($this->discount_amount ?? 0),
            ] : null,
            'source' => $this->source ?? 'online',
            'ml_order_id' => $this->ml_order_id,
            'ml_shipment_id' => $this->ml_shipment_id,
            'ml_content_declared_at' => $this->ml_content_declared_at?->toDateTimeString(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];

        // Inclui os items completos se estiverem carregados
        if ($this->relationLoaded('items')) {
            $data['items'] = $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'image_url' => $item->product->image_url,
                    ] : null,
                    'variant' => $item->variant ? [
                        'id' => $item->variant->id,
                        'sku' => $item->variant->sku,
                        'color' => $item->variant->color,
                        'size' => $item->variant->size,
                    ] : null,
                    'kit' => $item->kit ? [
                        'id' => $item->kit->id,
                        'name' => $item->kit->name,
                        'description' => $item->kit->description,
                        'total_quantity' => $item->kit->total_quantity,
                        'items' => $item->kit->items ? $item->kit->items->map(function ($kitItem) {
                            return [
                                'id' => $kitItem->id,
                                'quantity' => $kitItem->quantity,
                                'variant' => $kitItem->variant ? [
                                    'id' => $kitItem->variant->id,
                                    'sku' => $kitItem->variant->sku,
                                    'color' => $kitItem->variant->color,
                                    'size' => $kitItem->variant->size,
                                ] : null,
                            ];
                        }) : [],
                    ] : null,
                ];
            });
        }

        return $data;
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending'                 => 'Pendente',
            'approved'                => 'Aprovado',
            'delivered'               => 'Entregue',
            'rejected'                => 'Rejeitado',
            'cancelled'               => 'Cancelado',
            'refunded'                => 'Reembolsado',
            'cancellation_requested'  => 'Cancelamento Solicitado',
            default => 'Desconhecido',
        };
    }

    private function getPaymentMethodLabel(): string
    {
        return match($this->payment_method) {
            'pix' => 'PIX',
            'credit_card' => 'Cartão de Crédito',
            'cash' => 'Dinheiro',
            'pix_manual' => 'PIX',
            'credit_card_machine' => 'Cartão de Crédito',
            'debit_card' => 'Cartão de Débito',
            default => $this->payment_method,
        };
    }

    private function getShippingStatusLabel(): ?string
    {
        if (!$this->shipping_status) {
            return null;
        }

        return match($this->shipping_status) {
            'pending' => 'Pendente',
            'paid' => 'Etiqueta paga',
            'generated' => 'Etiqueta gerada',
            'posted' => 'Postado',
            'in_transit' => 'Em trânsito',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            'returned' => 'Devolvido',
            default => $this->shipping_status,
        };
    }
}
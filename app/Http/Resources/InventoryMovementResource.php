<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'reason' => $this->reason,
            'reason_label' => $this->getReasonLabel(),
            'description' => $this->getDescription(),
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'variant' => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'size' => $this->variant?->size,
                'color' => $this->variant?->color,
                'product_name' => $this->variant?->product?->name,
            ],
            'order' => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'status' => $this->order?->status,
            ],
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'performed_by' => $this->performer
                ? trim($this->performer->name . ' ' . $this->performer->last_name)
                : $this->user_id,
            'can_revert' => $this->canBeReverted(),
            'reversed_at' => $this->reversed_at,
            'reversed_at_formatted' => $this->reversed_at?->format('d/m/Y H:i'),
            'reversed_by' => $this->reversed_by,
            'reversal_of_id' => $this->reversal_of_id,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at?->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at,
        ];
    }

    private function getTypeLabel(): string
    {
        return match ($this->type) {
            'in' => 'Entrada',
            'out' => 'Saída',
            'adjustment' => 'Ajuste',
            default => $this->type,
        };
    }

    private function getReasonLabel(): string
    {
        return match ($this->reason) {
            'sale' => 'Venda',
            'cancellation' => 'Cancelamento',
            'refund' => 'Reembolso',
            'manual_add' => 'Adição manual',
            'manual_remove' => 'Remoção manual',
            'manual_set' => 'Ajuste manual',
            default => $this->reason ?? 'Não especificado',
        };
    }

    private function getDescription(): string
    {
        $productName = $this->variant?->product?->name ?? 'Produto';
        $variantInfo = '';

        if ($this->variant) {
            $parts = [];
            if ($this->variant->color) $parts[] = $this->variant->color;
            if ($this->variant->size) $parts[] = $this->variant->size;
            if (!empty($parts)) {
                $variantInfo = ' (' . implode(', ', $parts) . ')';
            }
        }

        $quantity = $this->quantity;
        $typeLabel = $this->getTypeLabel();
        $reasonLabel = $this->getReasonLabel();

        $description = match ($this->reason) {
            'sale' => "Venda de {$quantity} unidade(s) de {$productName}{$variantInfo}",
            'cancellation' => "Devolução de {$quantity} unidade(s) de {$productName}{$variantInfo} por cancelamento",
            'refund' => "Devolução de {$quantity} unidade(s) de {$productName}{$variantInfo} por reembolso",
            'manual_add' => "Entrada manual de {$quantity} unidade(s) de {$productName}{$variantInfo}",
            'manual_remove' => "Saída manual de {$quantity} unidade(s) de {$productName}{$variantInfo}",
            'manual_set' => "Ajuste de estoque de {$productName}{$variantInfo}: {$this->stock_before} → {$this->stock_after}",
            default => "{$typeLabel} de {$quantity} unidade(s) de {$productName}{$variantInfo}",
        };

        if ($this->order) {
            $description .= " - Pedido #{$this->order->order_number}";
        }

        return $description;
    }
}

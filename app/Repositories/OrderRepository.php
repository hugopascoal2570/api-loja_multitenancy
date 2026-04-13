<?php

namespace App\Repositories;

use App\Models\Order;

class OrderRepository
{
    public function findWithRelations(string $orderId): Order
    {
        return Order::with([
            'cart',
            'items.product',
            'items.variant',
            'items.kit.items.variant',
            'user'
        ])->findOrFail($orderId);
    }

    public function save(Order $order): void
    {
        $order->save();
    }


    public function getAllWithRelations(?string $period = null, ?string $status = null, ?string $source = null, ?string $deliveryMethod = null, int $perPage = 50)
    {
        $query = Order::with(['items.product', 'items.variant', 'items.kit', 'user']);

        if ($period) {
            $date = match($period) {
                '24h' => now()->subHours(24),
                '48h' => now()->subHours(48),
                '3d' => now()->subDays(3),
                '1w' => now()->subWeek(),
                '1m' => now()->subMonth(),
                '3m' => now()->subMonths(3),
                '6m' => now()->subMonths(6),
                '1y' => now()->subYear(),
                default => null,
            };

            if ($date) {
                $query->where('created_at', '>=', $date);
            }
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($source === 'counter') {
            // balcão puro: exclui pedidos de excursão (excursion_info preenchido)
            $query->where('source', 'counter')->whereNull('excursion_info');
        } elseif ($source === 'online') {
            $query->where('source', 'online');
        } elseif ($source === 'mercadolivre') {
            $query->where('source', 'mercadolivre');
        }

        // delivery_method=excursion OU presença de excursion_info
        if ($deliveryMethod === 'excursion') {
            $query->where(function ($q) {
                $q->where('delivery_method', 'excursion')->orWhereNotNull('excursion_info');
            });
        } elseif ($deliveryMethod) {
            $query->where('delivery_method', $deliveryMethod);
        }

        return $query->latest()->paginate($perPage);
    }

    public function updateStatus(string $orderId, string $status): Order
    {
        $order = $this->findWithRelations($orderId);
        $order->status = $status;
        $order->save();
        return $order->fresh(['items.product', 'items.variant', 'items.kit', 'user']);
    }
}
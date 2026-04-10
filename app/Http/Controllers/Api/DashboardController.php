<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Retorna estatísticas do dashboard administrativo
     */
    public function index(): JsonResponse
    {
        // Total de usuários
        $totalUsers = User::count();
        $previousMonthUsers = User::where('created_at', '<', Carbon::now()->startOfMonth())->count();
        $currentMonthUsers = $totalUsers - $previousMonthUsers;
        $usersGrowth = $previousMonthUsers > 0
            ? round((($currentMonthUsers - $previousMonthUsers) / $previousMonthUsers) * 100, 1)
            : 0;

        // Total de produtos ativos
        $totalProducts = Product::where('active', true)->count();
        $productsThisWeek = Product::where('active', true)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();

        // Total de pedidos
        $totalOrders = Order::count();
        $ordersLastWeek = Order::where('created_at', '>=', Carbon::now()->subWeek()->startOfWeek())
            ->where('created_at', '<', Carbon::now()->startOfWeek())
            ->count();
        $ordersThisWeek = Order::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $ordersGrowth = $ordersLastWeek > 0
            ? round((($ordersThisWeek - $ordersLastWeek) / $ordersLastWeek) * 100, 1)
            : 0;

        // Receita total (apenas pedidos aprovados)
        $totalRevenue = Order::whereIn('status', ['approved', 'shipped', 'delivered'])
            ->sum('total_amount');

        $revenueLastMonth = Order::whereIn('status', ['approved', 'shipped', 'delivered'])
            ->where('created_at', '>=', Carbon::now()->subMonth()->startOfMonth())
            ->where('created_at', '<', Carbon::now()->startOfMonth())
            ->sum('total_amount');

        $revenueThisMonth = Order::whereIn('status', ['approved', 'shipped', 'delivered'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('total_amount');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        // Pedidos recentes (últimos 4)
        $recentOrders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($order) {
                $itemsDescription = $order->items->map(function ($item) {
                    return $item->product ? $item->product->name : 'Produto removido';
                })->take(2)->implode(' • ');

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $this->getStatusLabel($order->status),
                    'customer_name' => $order->user ? $order->user->name : 'Visitante',
                    'items_description' => $itemsDescription,
                    'total_amount' => (float) $order->total_amount,
                    'created_at' => $order->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'data' => [
                'statistics' => [
                    'users' => [
                        'total' => $totalUsers,
                        'growth' => $usersGrowth,
                        'growth_label' => $usersGrowth >= 0
                            ? "+{$usersGrowth}% em relação ao mês anterior"
                            : "{$usersGrowth}% em relação ao mês anterior",
                    ],
                    'products' => [
                        'total' => $totalProducts,
                        'new_this_week' => $productsThisWeek,
                        'growth_label' => "+{$productsThisWeek} novos produtos esta semana",
                    ],
                    'orders' => [
                        'total' => $totalOrders,
                        'growth' => $ordersGrowth,
                        'growth_label' => $ordersGrowth >= 0
                            ? "+{$ordersGrowth}% em relação à semana anterior"
                            : "{$ordersGrowth}% em relação à semana anterior",
                    ],
                    'revenue' => [
                        'total' => (float) $totalRevenue,
                        'total_formatted' => 'R$ ' . number_format($totalRevenue, 2, ',', '.'),
                        'growth' => $revenueGrowth,
                        'growth_label' => $revenueGrowth >= 0
                            ? "+{$revenueGrowth}% em relação ao mês anterior"
                            : "{$revenueGrowth}% em relação ao mês anterior",
                    ],
                ],
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    /**
     * Retorna o label do status do pedido
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            default => 'Desconhecido',
        };
    }
}

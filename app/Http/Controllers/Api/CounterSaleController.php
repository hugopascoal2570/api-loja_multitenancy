<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Jobs\SendOrderNotificationsJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CounterSaleController extends Controller
{
    /**
     * Registra uma venda de balcão (presencial)
     * POST /api/counter-sales
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'nullable|string|max:20',
            'payment_method'   => 'required|in:cash,pix_manual,credit_card_machine,debit_card',
            'discount_amount'  => 'nullable|numeric|min:0',
            'items'            => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid|exists:product_variants,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Validar estoque de cada variante
        foreach ($validated['items'] as $item) {
            $variant = ProductVariant::find($item['variant_id']);
            if ($variant->stock < $item['quantity']) {
                return response()->json([
                    'message' => "Estoque insuficiente para o produto \"{$variant->product->name}\" ({$variant->color} / {$variant->size}). Disponível: {$variant->stock}.",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($validated) {
            $discountAmount = $validated['discount_amount'] ?? 0;
            $subtotal = collect($validated['items'])->sum(fn($i) => $i['unit_price'] * $i['quantity']);
            $totalAmount = round($subtotal - $discountAmount, 2);

            // Cria com 'pending' para os itens existirem antes do observer decrementar
            $order = Order::create([
                'order_number'    => strtoupper(Str::random(10)),
                'source'          => 'counter',
                'customer_name'   => $validated['customer_name'],
                'customer_phone'  => $validated['customer_phone'] ?? null,
                'payment_method'  => $validated['payment_method'],
                'status'          => 'pending',
                'delivery_method' => 'counter',
                'delivery_fee'    => 0,
                'discount_amount' => $discountAmount,
                'total_amount'    => $totalAmount,
            ]);

            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'type'       => 'product',
                    'color'      => $variant->color,
                    'size'       => $variant->size,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price'=> round($item['unit_price'] * $item['quantity'], 2),
                ]);
            }

            // Atualiza para 'approved' — o observer decrementa o estoque com os itens já criados
            $order->update(['status' => 'approved']);
            // Marca como entregue (venda presencial já foi entregue no ato)
            $order->update(['status' => 'delivered']);

            return $order;
        });

        $order->load(['items.product', 'items.variant']);

        SendOrderNotificationsJob::dispatch($order);

        return response()->json([
            'message' => 'Venda de balcão registrada com sucesso.',
            'order'   => new OrderResource($order),
        ], 201);
    }

    /**
     * Lista vendas de balcão
     * GET /api/counter-sales
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        $orders = Order::with(['items.product', 'items.variant'])
            ->where('source', 'counter')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * Detalhe de uma venda de balcão
     * GET /api/counter-sales/{id}
     */
    public function show(string $id)
    {
        $order = Order::with(['items.product', 'items.variant'])
            ->where('source', 'counter')
            ->findOrFail($id);

        return new OrderResource($order);
    }
}

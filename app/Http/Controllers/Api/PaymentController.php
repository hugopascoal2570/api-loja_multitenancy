<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendOrderNotificationsJob;
use App\Models\Cart;
use App\Models\DeliverySetting;
use App\Models\ProductVariant;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\MelhorEnvioService;
use App\Services\MercadoPagoService;
use App\Services\CouponService;
use App\Repositories\DeliverySettingRepository;

class PaymentController extends Controller
{
    public function __construct(
        protected MercadoPagoService $service,
        protected CouponService $couponService
    ) {}

    public function create(Request $request)
    {
        $storeSetting = DeliverySetting::current();
        if ($storeSetting && !$storeSetting->is_store_open) {
            return response()->json([
                'message' => 'Loja temporariamente fechada para vendas.',
            ], 403);
        }

        $validated = $request->validate([
            'cart_token'       => 'required|string|exists:carts,token',
            'payment_method'   => 'required|in:pix,credit_card',
            'card_token'       => 'required_if:payment_method,credit_card',
            'installments'     => 'nullable|integer|min:1',
            'device_id'        => 'nullable|string',
            'delivery_method'  => 'required|in:pickup,excursion,shipping',
            'address_id'       => 'nullable|string|exists:user_addresses,id',
            'excursion_info'   => 'required_if:delivery_method,excursion|nullable|string|min:10|max:500',
            'shipping_service_id' => 'nullable|integer',
            'zip_code'         => 'nullable|string|min:8|max:9',
            'coupon_code'      => 'nullable|string|max:50',
        ]);

        $cart = Cart::where('token', $validated['cart_token'])
            ->with(['items.product.category', 'items.variant', 'user'])
            ->firstOrFail();

        // Pega o usuário autenticado
        $authenticatedUser = $request->user();

        // Verifica se há um usuário autenticado
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Você precisa estar logado para finalizar a compra.'], 401);
        }

        // Associa o carrinho ao usuário autenticado se ainda não estiver associado
        if (!$cart->user_id) {
            $cart->user_id = $authenticatedUser->id;
            $cart->save();
            $cart->load('user'); // Recarrega a relação
        }

        // Verifica se o carrinho pertence ao usuário autenticado
        if ($cart->user_id !== $authenticatedUser->id) {
            return response()->json(['message' => 'Este carrinho não pertence a você.'], 403);
        }

        try {
            // Valida estoque com SELECT FOR UPDATE dentro de uma transação curta.
            // Reduz a janela de race condition (TOCTOU) antes de chamar o MercadoPago.
            $stockError = null;
            DB::transaction(function () use ($cart, &$stockError) {
                foreach ($cart->items as $item) {
                    if (!$item->variant_id) continue;
                    $variant = ProductVariant::lockForUpdate()->find($item->variant_id);
                    if (!$variant || $variant->stock < $item->quantity) {
                        $stockError = [
                            'message' => "Produto sem estoque suficiente: {$item->product->name}",
                            'error'   => 'Estoque disponível: ' . ($variant?->stock ?? 0) . ', Quantidade solicitada: ' . $item->quantity,
                        ];
                        return;
                    }
                }
            });
            if ($stockError) {
                return response()->json($stockError, 422);
            }

            // Busca o endereço de entrega selecionado
            $shippingAddressData = null;
            if ($validated['delivery_method'] === 'shipping') {
                if (!empty($validated['address_id'])) {
                    $userAddress = UserAddress::where('id', $validated['address_id'])
                        ->where('user_id', $authenticatedUser->id)
                        ->first();

                    if (!$userAddress) {
                        return response()->json(['message' => 'Endereço de entrega não encontrado.'], 404);
                    }
                } else {
                    // Fallback: usa endereço padrão do usuário
                    $userAddress = $authenticatedUser->defaultAddress();
                }

                if ($userAddress) {
                    $shippingAddressData = [
                        'shipping_address'        => $userAddress->address,
                        'shipping_number'          => $userAddress->number,
                        'shipping_neighborhood'    => $userAddress->neighborhood,
                        'shipping_complement'      => $userAddress->complement,
                        'shipping_city'            => $userAddress->city,
                        'shipping_state'           => $userAddress->state,
                        'shipping_zip_code'        => $userAddress->zip_code,
                        'shipping_recipient_name'  => $userAddress->recipient_name ?? ($authenticatedUser->name . ' ' . ($authenticatedUser->last_name ?? '')),
                        'shipping_phone'           => $userAddress->phone ?? $authenticatedUser->phone,
                    ];
                }
            }

            // Dados do pagador (usuário autenticado)
            $payerData = [
                'email'      => $cart->user->email,
                'first_name' => $cart->user->name,
                'last_name'  => $cart->user->last_name ?? '',
                'cpf'        => $cart->user->cpf ?? null,
            ];

            // Calcula o valor total incluindo a taxa de entrega
            $deliveryRepo = new DeliverySettingRepository();
            $setting = DeliverySetting::current();

            $deliveryFee = 0;
            $shippingServiceId = null;
            $shippingServiceName = null;
            $shippingEstimatedDays = null;

            if ($validated['delivery_method'] !== 'pickup') {
                $isDynamic = $setting && $setting->is_dynamic_shipping_enabled;

                if ($validated['delivery_method'] === 'shipping' && $isDynamic && !empty($validated['shipping_service_id']) && !empty($validated['zip_code'])) {
                    // Frete dinamico via Melhor Envio
                    $melhorEnvio = new MelhorEnvioService();
                    $cart->load('items.kit');
                    $cartItems = $cart->items->map(fn($item) => [
                        'product' => $item->product,
                        'kit' => $item->kit,
                        'quantity' => $item->quantity,
                    ])->toArray();

                    $shippingOption = $melhorEnvio->getShippingOption(
                        $validated['zip_code'],
                        $cartItems,
                        $validated['shipping_service_id']
                    );

                    if (!$shippingOption) {
                        return response()->json([
                            'message' => 'Opcao de frete selecionada nao esta mais disponivel. Recalcule o frete.',
                        ], 422);
                    }

                    $deliveryFee = $shippingOption['price'];
                    $shippingServiceId = $shippingOption['id'];
                    $shippingServiceName = $shippingOption['name'] . ' - ' . $shippingOption['company'];
                    $shippingEstimatedDays = $shippingOption['days'];
                } else {
                    // Frete taxa fixa
                    $deliveryFee = $deliveryRepo->getDeliveryFee();
                }
            }

            $subtotal = $cart->items->sum('total_price');

            // Valida e aplica cupom se fornecido
            $coupon = null;
            $discountAmount = 0;

            if (!empty($validated['coupon_code'])) {
                $couponValidation = $this->couponService->validate($validated['coupon_code'], $authenticatedUser->id);

                if (!$couponValidation['valid']) {
                    return response()->json([
                        'message' => $couponValidation['message']
                    ], 422);
                }

                $coupon = $couponValidation['coupon'];
                $discountAmount = $this->couponService->calculateDiscount($coupon, $subtotal);
            }

            $totalAmount = round($subtotal + $deliveryFee - $discountAmount, 2);

            // Valida valor mínimo do pedido (após desconto do cupom)
            $minimumOrderValue = $deliveryRepo->getMinimumOrderValue();
            if ($minimumOrderValue > 0 && $totalAmount < $minimumOrderValue) {
                $minimumMessage = $deliveryRepo->getMinimumOrderMessage()
                    ?? "O valor mínimo do pedido é R$ " . number_format($minimumOrderValue, 2, ',', '.');

                return response()->json([
                    'message' => $minimumMessage,
                    'minimum_order_value' => $minimumOrderValue,
                    'current_total' => $totalAmount,
                    'difference' => round($minimumOrderValue - $totalAmount, 2),
                ], 422);
            }

            $payment = $this->service->createPayment([
                'amount'         => $totalAmount,
                'email'          => $payerData['email'],
                'first_name'     => $payerData['first_name'],
                'last_name'      => $payerData['last_name'],
                'cpf'            => $payerData['cpf'],
                'payment_method' => $validated['payment_method'],
                'card_token'     => $validated['card_token'] ?? null,
                'installments'   => $validated['installments'] ?? 1,
                'device_id'      => $validated['device_id'] ?? null,
                'ip'             => $request->ip(),
                'shipping_address' => $shippingAddressData,
            ], $cart);

            $orderData = [
                'payment_method'  => $validated['payment_method'],
                'id'              => $payment['id'],
                'status'          => $payment['status'],
                'delivery_method' => $validated['delivery_method'],
                'delivery_fee'    => $deliveryFee,
                'shipping_service_id'     => $shippingServiceId,
                'shipping_service_name'   => $shippingServiceName,
                'shipping_estimated_days' => $shippingEstimatedDays,
                'excursion_info'  => $validated['excursion_info'] ?? null,
                'coupon_id'       => $coupon?->id,
                'coupon_code'     => $coupon?->code,
                'discount_amount' => $discountAmount,
            ];

            // Adiciona snapshot do endereço de entrega ao pedido
            if ($shippingAddressData) {
                $orderData = array_merge($orderData, $shippingAddressData);
            }

            $order = $this->service->createFromCart($cart, $orderData);

            // Registra o uso do cupom após a criação do pedido
            if ($coupon) {
                $this->couponService->apply($coupon, $authenticatedUser->id, $order->id);
            }

            // Notificar admin se pedido já nasce aprovado (cartao de credito)
            if ($order->status === 'approved') {
                SendOrderNotificationsJob::dispatch($order->id);
            }

            return response()->json([
                'message' => 'Pagamento e pedido criados com sucesso.',
                'payment' => $payment,
                'order'   => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar pagamento', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Erro ao gerar pagamento. Tente novamente.',
            ], 500);
        }
    }

    public function status($paymentId)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            // Buscar o pedido pelo payment_id e verificar se pertence ao usuário
            $order = $user->orders()->where('payment_id', $paymentId)->first();

            if (!$order) {
                return response()->json(['message' => 'Pagamento não encontrado'], 404);
            }

            // Retornar os dados do pedido
            return response()->json([
                'payment_id' => $order->payment_id,
                'status' => $order->status,
                'status_label' => $this->getStatusLabel($order->status),
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar status do pagamento', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Erro ao buscar status do pagamento.',
            ], 500);
        }
    }

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => 'Desconhecido',
        };
    }
}
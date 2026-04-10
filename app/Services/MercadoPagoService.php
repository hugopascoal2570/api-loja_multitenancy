<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\StoreConfiguration;
use App\Repositories\DeliverySettingRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Client\Payment\PaymentRefundClient;

class MercadoPagoService
{
    public function __construct()
    {
        $config = StoreConfiguration::current();
        $token  = $config->mp_access_token ?? config('services.mercadopago.access_token');
        MercadoPagoConfig::setAccessToken($token);
    }

    /**
     * Mapeia o status do Mercado Pago para o status aceito pela tabela orders
     */
    public function mapPaymentStatus(string $mercadoPagoStatus): string
    {
        return match ($mercadoPagoStatus) {
            'approved' => 'approved',
            'pending', 'in_process', 'authorized' => 'pending',
            'rejected', 'cancelled' => 'rejected',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }

    public function createPayment(array $payload, Cart $cart): array
    {
        $requestOptions = new RequestOptions();
        $client = new PaymentClient();

        $deviceId = $payload['device_id'] ?? null;
        $ipAddress = $payload['ip'] ?? '127.0.0.1';

        // 1. Criamos a lista detalhada de itens para o additional_info
        $items = $cart->items->map(function ($item) use ($cart) {
            $productName = optional($item->product)->name ?? 'Produto';

            // Construir um ID único que inclua informações da variante
            // Isso garante que o Mercado Pago trate cada variante como item separado
            $uniqueId = (string) ($item->variant_id ?? $item->product_id);

            if ($item->variant) {
                $variantDetails = [];
                if ($item->variant->color) $variantDetails[] = "Cor: {$item->variant->color}";
                if ($item->variant->size) $variantDetails[] = "Tamanho: {$item->variant->size}";
                if (!empty($variantDetails)) {
                    $productName .= ' (' . implode(', ', $variantDetails) . ')';
                }

                // IMPORTANTE: Adicionar SKU ao ID para garantir unicidade
                // Formato: variant_id-SKU (ex: uuid-CALC-BEJE-PP)
                if ($item->variant->sku) {
                    $uniqueId .= '-' . $item->variant->sku;
                }
            }

            return [
                'id'          => $uniqueId,
                'title'       => $productName,
                'description' => mb_strimwidth(optional($item->product)->description ?? 'Sem descrição', 0, 250, "..."),
                'category_id' => 'others',
                'quantity'    => (int) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
            ];
        })->values()->toArray();

        // 2. SOLUÇÃO PARA O HISTÓRICO: Concatenamos os nomes dos produtos para o campo principal
        // O Mercado Pago muitas vezes usa o primeiro item ou um resumo para o extrato.
        // Vamos garantir que o "description" principal da transação contenha um resumo de todos os itens.
        $summaryItems = $cart->items->map(function($item) {
            return "{$item->quantity}x " . (optional($item->product)->name ?? 'Produto');
        })->implode(', ');
        
        $transactionDescription = mb_strimwidth("Pedido: " . $summaryItems, 0, 250, "...");

        // Dados do usuário
        $u = $cart->user;
        $rawPhone = (string) ($u->phone ?? '');
        $digits   = preg_replace('/\D+/', '', $rawPhone);
        $areaCode = strlen($digits) >= 10 ? substr($digits, 0, 2) : null;
        $number   = strlen($digits) >= 10 ? substr($digits, 2)    : ($digits ?: null);

        // Usa endereço de entrega selecionado, senão fallback para endereço do perfil
        $addr = $payload['shipping_address'] ?? null;
        $zip          = $addr['shipping_zip_code'] ?? $u->zip_code ?? null;
        $streetName   = $addr['shipping_address'] ?? $u->address ?? null;
        $streetNumber = $addr ? ($addr['shipping_number'] ?? null) : (isset($u->number) && is_numeric($u->number) ? (int) $u->number : null);
        $city         = $addr['shipping_city'] ?? $u->city ?? null;
        $state        = $addr['shipping_state'] ?? $u->state ?? null;
        if (is_string($streetNumber) && is_numeric($streetNumber)) {
            $streetNumber = (int) $streetNumber;
        }

        $baseData = [
            "transaction_amount" => round((float) $payload['amount'], 2),
            "description"        => $transactionDescription, // <<< ADICIONADO: Descrição global da transação
            "statement_descriptor" => "HTech Store",
            "payer" => [
                "email" => $payload['email'],
                "first_name" => $payload['first_name'],
                "last_name"  => $payload['last_name'],
                "identification" => [
                    "type" => "CPF",
                    "number" => preg_replace('/\D/', '', $payload['cpf'])
                ]
            ],
            "external_reference" => (string) Str::uuid(),
            "notification_url" => rtrim(env('APP_API_URL', env('APP_URL')), '/') . '/api/mercadopago/webhook',
            "additional_info" => [
                "items" => $items,
                "payer" => [
                    "first_name" => $payload['first_name'],
                    "last_name"  => $payload['last_name'],
                    "phone" => array_filter([
                        "area_code" => $areaCode,
                        "number"    => $number,
                    ]),
                    "address" => array_filter([
                        "zip_code"      => $zip,
                        "street_name"   => $streetName,
                        "street_number" => $streetNumber,
                    ])
                ],
                "shipments" => [
                    "receiver_address" => array_filter([
                        "zip_code"      => $zip,
                        "state_name"    => $state,
                        "city_name"     => $city,
                        "street_name"   => $streetName,
                        "street_number" => $streetNumber,
                    ])
                ],
                "ip_address" => $ipAddress
            ],
        ];

        // Adicionamos também aos metadados para garantir rastreabilidade total
        $baseData["metadata"] = [
            "items_summary" => $summaryItems,
            "cart_id" => $cart->id
        ];

        if ($deviceId) {
            $baseData["metadata"]["device_id"] = $deviceId;
        }

        if ($payload['payment_method'] === 'pix') {
            $baseData['payment_method_id'] = 'pix';
            $baseData['date_of_expiration'] = now()
                ->addMinutes(30)
                ->setTimezone('America/Sao_Paulo')
                ->format('Y-m-d\TH:i:s.000P');
        } elseif ($payload['payment_method'] === 'credit_card') {
            $baseData['token'] = $payload['card_token'];
            $baseData['installments'] = (int) ($payload['installments'] ?? 1);
        }

        // Log para debug: verificar se os itens estão sendo enviados corretamente
        \Log::info('Mercado Pago - Criando pagamento', [
            'items_count' => count($items),
            'items' => $items,
            'transaction_description' => $transactionDescription
        ]);

        try {
            $payment = $client->create($baseData, $requestOptions);

            $response = [
                'id' => (string) $payment->id,
                'status' => (string) $payment->status,
                'payment_type' => (string) $payment->payment_type_id,
                'method' => (string) $payment->payment_method_id,
            ];

            if ($payload['payment_method'] === 'pix') {
                $response['qr_code'] = $payment->point_of_interaction->transaction_data->qr_code ?? null;
                $response['qr_code_base64'] = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null;
            }

            return $response;
        } catch (MPApiException $e) {
            throw new \Exception('Erro ao criar pagamento: ' . json_encode($e->getApiResponse()->getContent()));
        }
    }

    public function createFromCart(Cart $cart, array $paymentData): Order
    {
        return DB::transaction(function () use ($cart, $paymentData) {
            // Usa o delivery_fee que já foi calculado no controller (considera pickup)
            $deliveryFee = $paymentData['delivery_fee'] ?? 0;

            $subtotal = $cart->items->sum('total_price');
            $discountAmount = $paymentData['discount_amount'] ?? 0;
            $totalAmount = round($subtotal + $deliveryFee - $discountAmount, 2);

            $order = Order::create([
                'cart_id'        => $cart->id,
                'order_number'   => strtoupper(Str::random(10)),
                'user_id'        => $cart->user_id,
                'total_amount'   => $totalAmount,
                'delivery_fee'   => $deliveryFee,
                'delivery_method'=> $paymentData['delivery_method'] ?? 'delivery',
                'shipping_address'        => $paymentData['shipping_address'] ?? null,
                'shipping_number'          => $paymentData['shipping_number'] ?? null,
                'shipping_neighborhood'    => $paymentData['shipping_neighborhood'] ?? null,
                'shipping_complement'      => $paymentData['shipping_complement'] ?? null,
                'shipping_city'            => $paymentData['shipping_city'] ?? null,
                'shipping_state'           => $paymentData['shipping_state'] ?? null,
                'shipping_zip_code'        => $paymentData['shipping_zip_code'] ?? null,
                'shipping_recipient_name'  => $paymentData['shipping_recipient_name'] ?? null,
                'shipping_phone'           => $paymentData['shipping_phone'] ?? null,
                'shipping_service_id'    => $paymentData['shipping_service_id'] ?? null,
                'shipping_service_name'  => $paymentData['shipping_service_name'] ?? null,
                'shipping_estimated_days'=> $paymentData['shipping_estimated_days'] ?? null,
                'coupon_id'      => $paymentData['coupon_id'] ?? null,
                'coupon_code'    => $paymentData['coupon_code'] ?? null,
                'discount_amount'=> $discountAmount,
                'excursion_info' => $paymentData['excursion_info'] ?? null,
                'payment_method' => $paymentData['payment_method'],
                'payment_id'     => $paymentData['id'],
                'status'         => $this->mapPaymentStatus($paymentData['status']),
            ]);

            foreach ($cart->items as $item) {
                $variant = $item->variant_id ? \App\Models\ProductVariant::find($item->variant_id) : null;

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'kit_id'     => $item->kit_id,
                    'type'       => $item->type,
                    'color'      => $variant->color ?? null,
                    'size'       => $variant->size ?? null,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price'=> $item->total_price,
                ]);
            }

            return $order;
        });
    }

    public function cancelAndRefund(Order $order, ?float $amount = null, ?string $reason = null): array
    {
        if (empty($order->payment_id)) {
            throw new \Exception('O pedido não possui um ID de pagamento para reembolso.');
        }

        DB::beginTransaction();
        try {
            $paymentClient = new PaymentClient();
            $payment = $paymentClient->get($order->payment_id);

            if ($payment->status === 'refunded' || $payment->status === 'cancelled') {
                $order->status = 'cancelled';
                $order->cancel_reason = $reason ?? 'Status já cancelado/reembolsado no Mercado Pago.';
                $order->canceled_at = now();
                $order->save();
                DB::commit();

                return [
                    'order' => $order->fresh(),
                    'refund' => ['message' => 'Este pagamento já havia sido reembolsado ou cancelado no Mercado Pago.'],
                ];
            }

            $refundableAmount = (float) $payment->transaction_details->total_paid_amount;
            $amountToRefund = $amount ?? $refundableAmount;

            if ($amountToRefund > $refundableAmount) {
                 throw new \Exception("Valor de estorno (R$ {$amountToRefund}) maior que o valor pago (R$ {$refundableAmount}).");
            }

            $refundClient = new PaymentRefundClient();
            $refund = $refundClient->refund($order->payment_id, $amountToRefund);

            if ($refund->status !== 'approved' && $refund->status !== 'in_process') {
                throw new \Exception("O reembolso no Mercado Pago falhou com o status: " . $refund->status);
            }

            $order->status = 'cancelled';
            $order->cancel_reason = $reason ?? 'Cancelado pelo usuário';
            $order->canceled_at = now();
            $order->refund_id = $refund->id;
            $order->refund_amount = $refund->amount;
            $order->save();

            DB::commit();

            return [
                'order' => $order->fresh(),
                'refund' => [
                    'id' => $refund->id,
                    'status' => $refund->status,
                    'amount' => $refund->amount,
                ],
            ];

        } catch (MPApiException $e) {
            DB::rollBack();
            $errorInfo = $e->getApiResponse()->getContent();
            throw new \Exception('Erro na API do Mercado Pago: ' . json_encode($errorInfo));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
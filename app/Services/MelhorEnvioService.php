<?php

namespace App\Services;

use App\Models\DeliverySetting;
use App\Models\Order;
use App\Models\StoreConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MelhorEnvioService
{
    private ?string $token;
    private string $baseUrl;
    private StoreConfiguration $storeConfig;

    public function __construct()
    {
        $this->storeConfig = StoreConfiguration::current();

        $this->token = $this->storeConfig->melhor_envio_token ?? config('services.melhor_envio.token');
        $isSandbox   = $this->storeConfig->melhor_envio_sandbox ?? config('services.melhor_envio.sandbox', true);
        $this->baseUrl = $isSandbox
            ? 'https://sandbox.melhorenvio.com.br'
            : 'https://melhorenvio.com.br';
    }

    public function isConfigured(): bool
    {
        $setting = DeliverySetting::current();

        return !empty($this->token)
            && $setting
            && $setting->is_dynamic_shipping_enabled
            && !empty($setting->origin_zip_code);
    }

    private function api()
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Clochic (contato@clochic.com.br)',
        ]);
    }

    // =========================================================================
    // STEP 1: Calcular frete
    // =========================================================================

    public function calculateShipping(string $destinationZip, array $cartItems): array
    {
        $setting = DeliverySetting::current();

        if (!$setting || empty($setting->origin_zip_code)) {
            return ['error' => 'CEP de origem nao configurado.', 'options' => []];
        }

        $originZip = preg_replace('/\D/', '', $setting->origin_zip_code);
        $destinationZip = preg_replace('/\D/', '', $destinationZip);

        $products = $this->buildProductsPayload($cartItems, $setting);

        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/shipment/calculate", [
                'from' => ['postal_code' => $originZip],
                'to' => ['postal_code' => $destinationZip],
                'products' => $products,
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao calcular frete.', 'options' => []];
            }

            return [
                'options' => $this->formatResponse($response->json()),
                'debug' => [
                    'from' => $originZip,
                    'to' => $destinationZip,
                    'products' => $products,
                ],
            ];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha na requisicao', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Erro ao comunicar com servico de frete.', 'options' => []];
        }
    }

    public function getShippingOption(string $destinationZip, array $cartItems, int $serviceId): ?array
    {
        $result = $this->calculateShipping($destinationZip, $cartItems);

        if (!empty($result['error']) && empty($result['options'])) {
            return null;
        }

        foreach ($result['options'] as $option) {
            if ($option['id'] === $serviceId) {
                return $option;
            }
        }

        return null;
    }

    // =========================================================================
    // STEP 2: Adicionar envio ao carrinho do ME
    // =========================================================================

    public function addToCart(Order $order, bool $reverse = false): array
    {
        $setting = DeliverySetting::current();

        $c    = $this->storeConfig;
        $from = [
            'name'             => config('app.name', 'Clochic'),
            'phone'            => $c->melhor_envio_phone            ?? config('services.melhor_envio.phone', ''),
            'email'            => $c->melhor_envio_email            ?? config('services.melhor_envio.email', ''),
            'document'         => $c->melhor_envio_document         ?? config('services.melhor_envio.document', ''),
            'company_document' => $c->melhor_envio_company_document ?? config('services.melhor_envio.company_document', ''),
            'state_register'   => $c->melhor_envio_state_register   ?? config('services.melhor_envio.state_register', ''),
            'address'          => $c->melhor_envio_address          ?? config('services.melhor_envio.address', ''),
            'complement'       => $c->melhor_envio_complement       ?? config('services.melhor_envio.complement', ''),
            'number'           => $c->melhor_envio_number           ?? config('services.melhor_envio.number', ''),
            'district'         => $c->melhor_envio_district         ?? config('services.melhor_envio.district', ''),
            'city'             => $c->melhor_envio_city             ?? config('services.melhor_envio.city', ''),
            'country_id'       => 'BR',
            'postal_code'      => preg_replace('/\D/', '', $setting->origin_zip_code),
            'state_abbr'       => $c->melhor_envio_state_abbr       ?? config('services.melhor_envio.state_abbr', ''),
        ];

        $to = [
            'name' => $order->shipping_recipient_name ?? $order->user->name,
            'phone' => $order->shipping_phone ?? $order->user->phone ?? '',
            'email' => $order->user->email ?? '',
            'document' => $order->user->cpf ? preg_replace('/\D/', '', $order->user->cpf) : '',
            'address' => $order->shipping_address ?? '',
            'complement' => $order->shipping_complement ?? '',
            'number' => $order->shipping_number ?? '',
            'district' => $order->shipping_neighborhood ?? '',
            'city' => $order->shipping_city ?? '',
            'country_id' => 'BR',
            'postal_code' => preg_replace('/\D/', '', $order->shipping_zip_code ?? ''),
            'state_abbr' => $order->shipping_state ?? '',
        ];

        // Monta produtos do pedido
        $products = [];
        $totalValue = 0;
        foreach ($order->items as $item) {
            $products[] = [
                'name' => $item->product->name ?? 'Produto',
                'quantity' => (string) $item->quantity,
                'unitary_value' => (string) $item->unit_price,
            ];
            $totalValue += $item->total_price;
        }

        // Monta volumes
        $volumes = $this->buildVolumesFromOrder($order, $setting);

        $payload = [
            'service' => (int) $order->shipping_service_id,
            'from' => $reverse ? $to : $from,
            'to' => $reverse ? $from : $to,
            'products' => $products,
            'volumes' => $volumes,
            'options' => [
                'insurance_value' => (float) $totalValue,
                'reverse' => $reverse,
                'non_commercial' => true,
            ],
        ];

        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/cart", $payload);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro ao adicionar ao carrinho', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'order_id' => $order->id,
                ]);
                return ['error' => 'Erro ao adicionar envio ao carrinho: ' . $response->body()];
            }

            $data = $response->json();

            return [
                'melhor_envio_order_id' => $data['id'],
                'protocol' => $data['protocol'] ?? null,
                'price' => $data['price'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha ao adicionar ao carrinho', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            return ['error' => 'Falha ao comunicar com Melhor Envio: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // STEP 3: Checkout (pagar etiqueta)
    // =========================================================================

    public function checkout(string $melhorEnvioOrderId): array
    {
        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/shipment/checkout", [
                'orders' => [$melhorEnvioOrderId],
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro no checkout', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao pagar etiqueta: ' . $response->body()];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha no checkout', ['error' => $e->getMessage()]);
            return ['error' => 'Falha no checkout: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // STEP 4: Gerar etiqueta
    // =========================================================================

    public function generateLabel(string $melhorEnvioOrderId): array
    {
        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/shipment/generate", [
                'orders' => [$melhorEnvioOrderId],
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro ao gerar etiqueta', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao gerar etiqueta: ' . $response->body()];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha ao gerar etiqueta', ['error' => $e->getMessage()]);
            return ['error' => 'Falha ao gerar etiqueta: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // STEP 5: Imprimir etiqueta
    // =========================================================================

    public function printLabel(string $melhorEnvioOrderId): array
    {
        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/shipment/print", [
                'mode' => 'public',
                'orders' => [$melhorEnvioOrderId],
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro ao imprimir etiqueta', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao imprimir etiqueta: ' . $response->body()];
            }

            $data = $response->json();

            return ['success' => true, 'url' => $data['url'] ?? null];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha ao imprimir etiqueta', ['error' => $e->getMessage()]);
            return ['error' => 'Falha ao imprimir etiqueta: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // STEP 6: Rastrear envio
    // =========================================================================

    public function tracking(array $melhorEnvioOrderIds): array
    {
        try {
            $response = $this->api()->post("{$this->baseUrl}/api/v2/me/shipment/tracking", [
                'orders' => $melhorEnvioOrderIds,
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro ao rastrear', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao rastrear envio: ' . $response->body()];
            }

            return $response->json();

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha ao rastrear', ['error' => $e->getMessage()]);
            return ['error' => 'Falha ao rastrear: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // STEP 7: Cancelar etiqueta
    // =========================================================================

    public function cancelLabel(string $melhorEnvioOrderId, int $reasonId = 2, string $description = 'Cancelamento solicitado pela loja'): array
    {
        try {
            $response = $this->api()->asJson()->post("{$this->baseUrl}/api/v2/me/shipment/cancel", [
                'orders' => [
                    'id'          => $melhorEnvioOrderId,
                    'reason_id'   => $reasonId,
                    'description' => $description,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Melhor Envio: erro ao cancelar', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['error' => 'Erro ao cancelar etiqueta: ' . $response->body()];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Throwable $e) {
            Log::error('Melhor Envio: falha ao cancelar', ['error' => $e->getMessage()]);
            return ['error' => 'Falha ao cancelar: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // Fluxo completo: carrinho + checkout + geração de etiqueta
    // =========================================================================

    public function purchaseLabel(Order $order): array
    {
        // Validações antes de enviar ao Melhor Envio
        $validation = $this->validateShipment($order);
        if ($validation) {
            return ['error' => $validation];
        }

        // 1. Adicionar ao carrinho
        $cartResult = $this->addToCart($order);
        if (isset($cartResult['error'])) {
            return $cartResult;
        }

        $meOrderId = $cartResult['melhor_envio_order_id'];

        // Salva o ID do ME no pedido
        $order->update([
            'melhor_envio_order_id' => $meOrderId,
            'melhor_envio_protocol' => $cartResult['protocol'],
        ]);

        // 2. Checkout (pagar)
        $checkoutResult = $this->checkout($meOrderId);
        if (isset($checkoutResult['error'])) {
            // Limpa o ID salvo para permitir nova tentativa após repor saldo
            $order->update(['melhor_envio_order_id' => null, 'melhor_envio_protocol' => null]);
            return $checkoutResult;
        }

        $order->update([
            'melhor_envio_paid_at' => now(),
            'shipping_status' => 'paid',
        ]);

        // 3. Gerar etiqueta
        $generateResult = $this->generateLabel($meOrderId);
        if (isset($generateResult['error'])) {
            return $generateResult;
        }

        $order->update([
            'melhor_envio_generated_at' => now(),
            'shipping_status' => 'generated',
        ]);

        // 4. Obter URL de impressão
        $printResult = $this->printLabel($meOrderId);

        $labelUrl = $printResult['url'] ?? null;
        if ($labelUrl) {
            $order->update(['melhor_envio_label_url' => $labelUrl]);
        }

        return [
            'success' => true,
            'melhor_envio_order_id' => $meOrderId,
            'protocol' => $cartResult['protocol'],
            'label_url' => $labelUrl,
        ];
    }

    // =========================================================================
    // Logística reversa completa
    // =========================================================================

    public function purchaseReverseLabel(Order $order, int $serviceId): array
    {
        // Sobrescreve temporariamente o service_id para a reversa (PAC ou SEDEX)
        $originalServiceId = $order->shipping_service_id;
        $order->shipping_service_id = $serviceId;

        // 1. Adicionar ao carrinho com reverse=true
        $cartResult = $this->addToCart($order, reverse: true);

        // Restaura
        $order->shipping_service_id = $originalServiceId;

        if (isset($cartResult['error'])) {
            return $cartResult;
        }

        $meOrderId = $cartResult['melhor_envio_order_id'];

        // 2. Checkout
        $checkoutResult = $this->checkout($meOrderId);
        if (isset($checkoutResult['error'])) {
            return $checkoutResult;
        }

        // 3. Gerar etiqueta (gera o código reverso)
        $generateResult = $this->generateLabel($meOrderId);
        if (isset($generateResult['error'])) {
            return $generateResult;
        }

        // 4. Buscar tracking info
        $trackingResult = $this->tracking([$meOrderId]);

        return [
            'success' => true,
            'melhor_envio_order_id' => $meOrderId,
            'protocol' => $cartResult['protocol'],
            'tracking' => $trackingResult[$meOrderId] ?? null,
        ];
    }

    // =========================================================================
    // Atualizar tracking de um pedido
    // =========================================================================

    public function syncTracking(Order $order): bool
    {
        if (empty($order->melhor_envio_order_id)) {
            return false;
        }

        $result = $this->tracking([$order->melhor_envio_order_id]);

        if (isset($result['error'])) {
            return false;
        }

        $info = $result[$order->melhor_envio_order_id] ?? null;
        if (!$info) {
            return false;
        }

        $updates = [];

        if (!empty($info['tracking'])) {
            $updates['tracking_code'] = $info['tracking'];
        }

        if (!empty($info['status'])) {
            $updates['shipping_status'] = $info['status'];
        }

        if (!empty($info['posted_at']) && empty($order->melhor_envio_posted_at)) {
            $updates['melhor_envio_posted_at'] = $info['posted_at'];
        }

        if (!empty($info['delivered_at']) && empty($order->melhor_envio_delivered_at)) {
            $updates['melhor_envio_delivered_at'] = $info['delivered_at'];
        }

        if (!empty($updates)) {
            $order->update($updates);
        }

        return true;
    }

    // =========================================================================
    // Validações
    // =========================================================================

    private function validateShipment(Order $order): ?string
    {
        $setting = DeliverySetting::current();
        $senderZip = preg_replace('/\D/', '', $setting->origin_zip_code ?? '');
        $recipientZip = preg_replace('/\D/', '', $order->shipping_zip_code ?? '');

        if ($senderZip && $recipientZip && $senderZip === $recipientZip) {
            return 'O CEP do destinatário não pode ser igual ao CEP do remetente.';
        }

        // Usa preferencialmente o CNPJ da empresa (14 dígitos) para comparação,
        // pois CNPJ nunca coincide com CPF de cliente (11 dígitos).
        // O CPF pessoal (document) só é usado se for CNPJ, evitando falso positivo
        // quando o remetente é também um cliente da loja.
        $companyDoc  = preg_replace('/\D/', '', $this->storeConfig->melhor_envio_company_document ?? config('services.melhor_envio.company_document', ''));
        $personalDoc = preg_replace('/\D/', '', $this->storeConfig->melhor_envio_document ?? config('services.melhor_envio.document', ''));
        $senderDoc   = strlen($companyDoc) === 14 ? $companyDoc : (strlen($personalDoc) === 14 ? $personalDoc : '');

        $recipientDoc = $order->user?->cpf ? preg_replace('/\D/', '', $order->user->cpf) : '';

        if ($senderDoc && $recipientDoc && $senderDoc === $recipientDoc) {
            return 'O CPF/CNPJ do destinatário não pode ser igual ao do remetente.';
        }

        if (empty($recipientZip)) {
            return 'O CEP do destinatário é obrigatório.';
        }

        if (empty($order->shipping_address)) {
            return 'O endereço do destinatário é obrigatório.';
        }

        if (empty($order->shipping_city)) {
            return 'A cidade do destinatário é obrigatória.';
        }

        return null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildProductsPayload(array $cartItems, DeliverySetting $setting): array
    {
        $products = [];

        foreach ($cartItems as $item) {
            $product = $item['product'] ?? null;
            $kit = $item['kit'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if ($kit) {
                $width = (float) ($kit->width ?? $product->width ?? $setting->default_width);
                $height = (float) ($kit->height ?? $product->height ?? $setting->default_height);
                $length = (float) ($kit->length ?? $product->length ?? $setting->default_length);
                $weight = (float) ($kit->weight ?? $product->weight ?? $setting->default_weight);
            } else {
                $width = (float) ($product->width ?? $setting->default_width);
                $height = (float) ($product->height ?? $setting->default_height);
                $length = (float) ($product->length ?? $setting->default_length);
                $weight = (float) ($product->weight ?? $setting->default_weight);
            }

            // Para kits, multiplicar pela quantidade de peças do kit
            $totalQuantity = $kit
                ? $quantity * ($kit->total_quantity ?? 1)
                : $quantity;

            $products[] = [
                'id' => $kit->id ?? $product->id ?? 'item',
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'weight' => $weight,
                'insurance_value' => 0,
                'quantity' => $totalQuantity,
            ];
        }

        return $products;
    }

    private function buildVolumesFromOrder(Order $order, DeliverySetting $setting): array
    {
        $totalWeight = 0;
        $maxWidth = 0;
        $maxHeight = 0;
        $maxLength = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            $kit = $item->kit ?? null;
            // Para kits: multiplicar pela quantidade de peças
            $qty = $item->quantity * ($kit ? ($kit->total_quantity ?? 1) : 1);

            $source = $kit ?? $product;
            $w = (float) ($source->weight ?? $product->weight ?? $setting->default_weight);
            $totalWeight += $w * $qty;

            $maxWidth = max($maxWidth, (float) ($source->width ?? $product->width ?? $setting->default_width));
            $maxHeight += (float) ($source->height ?? $product->height ?? $setting->default_height) * $qty;
            $maxLength = max($maxLength, (float) ($source->length ?? $product->length ?? $setting->default_length));
        }

        return [[
            'height' => max(2, (int) ceil($maxHeight)),
            'width' => max(11, (int) ceil($maxWidth)),
            'length' => max(16, (int) ceil($maxLength)),
            'weight' => max(0.3, round($totalWeight, 2)),
        ]];
    }

    private function formatResponse(array $apiResponse): array
    {
        $options = [];

        foreach ($apiResponse as $service) {
            if (!empty($service['error']) || empty($service['price'])) {
                continue;
            }

            $options[] = [
                'id' => (int) $service['id'],
                'name' => $service['name'] ?? '',
                'price' => (float) $service['price'],
                'discount' => (float) ($service['discount'] ?? 0),
                'days' => (int) ($service['delivery_time'] ?? 0),
                'company' => $service['company']['name'] ?? '',
                'company_picture' => $service['company']['picture'] ?? '',
            ];
        }

        usort($options, fn($a, $b) => $a['price'] <=> $b['price']);

        return $options;
    }
}

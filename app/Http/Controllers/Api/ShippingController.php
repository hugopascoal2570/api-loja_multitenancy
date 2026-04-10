<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\DeliverySetting;
use App\Services\MelhorEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(
        private MelhorEnvioService $melhorEnvio
    ) {}

    /**
     * Calcula opcoes de frete para o carrinho.
     * POST /api/shipping/calculate
     *
     * Body: { "zip_code": "01310100", "cart_token": "abc123" }
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip_code' => 'required|string|min:8|max:9',
            'cart_token' => 'required|string|exists:carts,token',
        ]);

        $setting = DeliverySetting::current();

        if (!$setting || !$setting->is_dynamic_shipping_enabled) {
            return response()->json([
                'dynamic_shipping' => false,
                'delivery_fee' => $setting ? (float) $setting->delivery_fee : 0,
                'options' => [],
            ]);
        }

        if (!$this->melhorEnvio->isConfigured()) {
            return response()->json([
                'message' => 'Servico de frete dinamico nao configurado.',
            ], 503);
        }

        $cart = Cart::where('token', $validated['cart_token'])
            ->with(['items.product', 'items.kit'])
            ->firstOrFail();

        $cartItems = $cart->items->map(fn($item) => [
            'product' => $item->product,
            'kit' => $item->kit,
            'quantity' => $item->quantity,
        ])->toArray();

        $result = $this->melhorEnvio->calculateShipping($validated['zip_code'], $cartItems);

        if (!empty($result['error']) && empty($result['options'])) {
            return response()->json([
                'message' => $result['error'],
                'options' => [],
            ], 422);
        }

        return response()->json([
            'dynamic_shipping' => true,
            'options' => $result['options'],
            'debug' => config('app.debug') ? ($result['debug'] ?? null) : null,
        ]);
    }
}

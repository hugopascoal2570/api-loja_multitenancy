<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Repositories\CartRepository;
use App\Http\Resources\CartItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\DTO\Cart\CartItemDTO;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use Illuminate\Support\Arr;

class CartController extends Controller
{
    public function __construct(protected CartRepository $cartRepository) {}

    public function index(Request $request)
    {
        $token = $request->header('cart-token');
        $cart = $this->cartRepository->findOrCreate($token);
        $items = $this->cartRepository->getItems($cart);

        return response()->json([
            'cart_id' => $cart->id,
            'cart_token' => $cart->token,
            'items' => CartItemResource::collection($items),
            'total_items' => $items->sum('quantity'),
            'subtotal' => $items->sum('total_price'),
        ]);

    }

    public function generateToken(): \Illuminate\Http\JsonResponse
    {
        $token = (string) Str::uuid();
        return response()->json(['token' => $token])
            ->withHeaders(['cart-token' => $token]);
    }

    public function add(StoreCartItemRequest $request)
    {
        $token = $request->header('cart-token') ?? (string) Str::uuid();
        $cart = $this->cartRepository->findOrCreate($token);

        $data = $request->validated();
        $items = [];

        $payloadItems = Arr::isAssoc($data) ? [$data] : $data;

        foreach ($payloadItems as $itemData) {
            $dto = new CartItemDTO(
                product_id: $itemData['product_id'],
                type: $itemData['type'],
                variant_id: $itemData['variant_id'] ?? null,
                kit_id: $itemData['kit_id'] ?? null,
                quantity: $itemData['quantity'],
                unit_price: 0,
                total_price: 0
            );

            $items[] = new CartItemResource(
                $this->cartRepository->addItem($cart, $dto)
            );
        }

        return response()->json([
            'cart_id'    => $cart->id,
            'cart_token' => $cart->token,
            'items'      => $items,
            'message'    => 'Itens adicionados ao carrinho.'
        ], 201)->withHeaders(['cart-token' => $token]);

    }

    public function clear(Request $request)
    {
        $token = $request->header('cart-token');
        $cart = $this->cartRepository->findOrCreate($token);
        $this->cartRepository->clear($cart);

        return response()->json(['message' => 'Carrinho limpo.']);
    }

    public function sync(Request $request)
    {
        $token = $request->header('cart-token') ?? (string) Str::uuid();
        $cart = $this->cartRepository->findOrCreate($token);

        $items = $request->validate([
            '*.product_id' => 'required|uuid',
            '*.type' => 'required|string|in:product,variant,kit',
            '*.variant_id' => 'nullable|uuid',
            '*.kit_id' => 'nullable|uuid',
            '*.quantity' => 'required|integer|min:1',
        ]);

        $dtos = collect($items)->map(fn($item) => new CartItemDTO(
            product_id: $item['product_id'],
            type: $item['type'],
            variant_id: $item['variant_id'] ?? null,
            kit_id: $item['kit_id'] ?? null,
            quantity: $item['quantity']
        ));

        $syncedItems = $this->cartRepository->syncItems($cart, $dtos);

        return response()->json([
            'cart_id' => $cart->id,
            'cart_token' => $cart->token,
            'items' => CartItemResource::collection($syncedItems),
            'total_items' => $syncedItems->sum('quantity'),
            'subtotal' => $syncedItems->sum('total_price'),
            'message' => 'Carrinho sincronizado com sucesso.'
        ])->withHeaders(['cart-token' => $token]);
    }

    public function remove(Request $request, int $itemId)
    {
        $token = $request->header('cart-token');
        $cart = $this->cartRepository->findOrCreate($token);
        $this->cartRepository->removeItem($cart, $itemId);

        return response()->json(['message' => 'Item removido.']);
    }
}

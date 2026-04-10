<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Account\UpdateAccountRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\Repositories\CartRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\DTO\Users\EditUserDTO;
use App\DTO\Cart\CartItemDTO;
use App\Http\Resources\OrderResource;
use App\Http\Resources\CartItemResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    public function __construct(
        private UserRepository $userRepository,
        private CartRepository $cartRepository,
    ) {}

    public function profile()
    {
        return new UserResource(Auth::user());
    }

    public function updateProfile(UpdateAccountRequest $request)
    {
        $user = Auth::user();

        $dto = new EditUserDTO(
            id: $user->id,
            name: $request->get('name'),
            last_name: $request->get('last_name'),
            password: $request->get('password'),
            cpf: $request->get('cpf'),
            phone: $request->get('phone'),
            address: $request->get('address'),
            number: $request->get('number'),
            neighborhood: $request->get('neighborhood'),
            complement: $request->get('complement'),
            city: $request->get('city'),
            state: $request->get('state'),
            zip_code: $request->get('zip_code'),
            is_admin: null
        );

        $this->userRepository->update($dto);

        return response()->json(['message' => 'Dados atualizados com sucesso.']);
    }
    
    public function orders()
    {
        $user = Auth::user();
        $orders = $this->userRepository->getOrdersByUserId($user->id);

        return OrderResource::collection($orders);
    }

    public function orderDetails($orderId)
    {
        $user = Auth::user();

        $order = $user->orders()
            ->with(['items.product', 'items.variant', 'items.kit'])
            ->findOrFail($orderId);

        return new OrderResource($order);
    }

    public function reorder(Request $request, $orderId)
    {
        $user = Auth::user();

        $order = $user->orders()
            ->with(['items.product', 'items.variant', 'items.kit'])
            ->findOrFail($orderId);

        if (!in_array($order->status, ['cancelled', 'rejected'])) {
            return response()->json(['message' => 'Apenas pedidos cancelados ou rejeitados podem ser recomprados.'], 422);
        }

        $token = $request->header('cart-token') ?? (string) Str::uuid();
        $cart  = $this->cartRepository->findOrCreate($token);

        $this->cartRepository->clear($cart);

        $removedItems = [];

        foreach ($order->items as $item) {
            $productName = $item->product?->name ?? 'Produto removido';

            // Produto inativo ou deletado
            if (!$item->product || !$item->product->active) {
                $removedItems[] = $productName;
                continue;
            }

            // Variante sem estoque
            if ($item->variant_id) {
                if (!$item->variant || $item->variant->stock < 1) {
                    $label = $item->variant
                        ? "{$productName} ({$item->variant->color} / {$item->variant->size})"
                        : $productName;
                    $removedItems[] = $label;
                    continue;
                }

                // Ajusta quantidade ao estoque disponível se necessário
                $quantity = min($item->quantity, $item->variant->stock);
            } else {
                $quantity = $item->quantity;
            }

            $this->cartRepository->addItem($cart, new CartItemDTO(
                product_id: $item->product_id,
                type:       $item->type,
                variant_id: $item->variant_id,
                kit_id:     $item->kit_id,
                quantity:   $quantity,
            ));
        }

        $items = $this->cartRepository->getItems($cart);

        $message = empty($removedItems)
            ? 'Carrinho recuperado com sucesso.'
            : 'Carrinho recuperado. Os seguintes itens foram removidos por falta de estoque: ' . implode(', ', $removedItems) . '.';

        return response()->json([
            'cart_id'       => $cart->id,
            'cart_token'    => $cart->token,
            'items'         => CartItemResource::collection($items),
            'total_items'   => $items->sum('quantity'),
            'subtotal'      => $items->sum('total_price'),
            'removed_items' => $removedItems,
            'message'       => $message,
        ])->withHeaders(['cart-token' => $cart->token]);
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Senha incorreta.'], 422);
        }

        // Anonimizar dados pessoais (pedidos ficam no histórico para controle fiscal)
        $user->update([
            'name'         => 'Usuário excluído',
            'last_name'    => null,
            'email'        => 'deleted_' . $user->id . '@excluido.local',
            'cpf'          => null,
            'phone'        => null,
            'address'      => null,
            'number'       => null,
            'neighborhood' => null,
            'complement'   => null,
            'city'         => null,
            'state'        => null,
            'zip_code'     => null,
        ]);

        $user->tokens()->delete();

        Log::info('Conta excluída por solicitação do titular (LGPD)', ['user_id' => $user->id]);

        return response()->json(['message' => 'Conta excluída com sucesso. Seus dados pessoais foram removidos.']);
    }
}
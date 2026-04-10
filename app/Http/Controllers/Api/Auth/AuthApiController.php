<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\AuthApiRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\Auth\RegisterUserRequest;
use Illuminate\Support\Str;
use App\DTO\Users\CreateUserDTO;
use App\Models\Cart;
use App\Models\Role;

class AuthApiController extends Controller
{
    public function __construct(private UserRepository $userRepository)
    { 
    }

    public function auth(AuthApiRequest $request)
    {
        $user = $this->userRepository->findByEmail($request->email);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // ── Resolve contexto de tenant e papel do usuário ─────────────────────
        $tenant  = app()->bound('tenant') ? app('tenant') : null;
        $role    = null;
        $context = 'customer'; // padrão: comprador

        if ($user->isPlatformSuperAdmin()) {
            // Super admin da plataforma: acesso irrestrito
            $context = 'platform_super_admin';
            $role    = Role::PLATFORM_SUPER_ADMIN;
        } elseif ($tenant) {
            $tenantUser = $user->tenantUserFor($tenant);

            if ($tenantUser) {
                // Staff da loja (owner / manager / employee)
                $context = 'staff';
                $role    = $tenantUser->role->name;
            }
            // Sem vínculo = cliente comprador neste tenant (context permanece 'customer')
        }

        // ── Cria token com contexto embutido nas abilities ─────────────────────
        // As abilities permitem que o middleware de autorização saiba o papel
        // sem precisar consultar o banco a cada requisição.
        $abilities = array_filter([
            "role:{$role}",
            $tenant ? "tenant:{$tenant->id}" : null,
            "context:{$context}",
        ]);

        $user->tokens()->delete();
        $token = $user->createToken($request->device_name, array_values($abilities))->plainTextToken;

        // ── Associa carrinho anônimo ao usuário logado ─────────────────────────
        $cartToken = $request->header('cart-token');
        if ($cartToken) {
            $cart = Cart::where('token', $cartToken)->first();
            if ($cart && ! $cart->user_id) {
                $cart->user_id = $user->id;
                $cart->save();
            }
        }

        return response()->json([
            'token'   => $token,
            'context' => $context,
            'role'    => $role,
        ]);
    }

    public function me()
    {
        $user = Auth::user();
        $user->load('permissions');
        return new UserResource($user);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();
        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function register(RegisterUserRequest $request)
    {
    $data = $request->validated();

    $user = $this->userRepository->createNew(new CreateUserDTO(
        name: $data['name'],
        last_name: null,
        email: $data['email'],
        password: $data['password'],
        cpf: null,
        phone: $data['phone'] ?? null,
        address: null,
        number: null,
        neighborhood: null,
        complement: null,
        city: null,
        state: null,
        zip_code: null,
        is_admin: false
    ));

    // Associar carrinho, se houver token
    $cartToken = $request->header('cart-token');
    if ($cartToken) {
        $cart = Cart::where('token', $cartToken)->first();
        if ($cart && !$cart->user_id) {
            $cart->user_id = $user->id;
            $cart->save();
        }
    }

    $token = $user->createToken($data['device_name'])->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => new UserResource($user),
    ], Response::HTTP_CREATED)->withHeaders(['cart-token' => $cartToken ?? (string) Str::uuid()]);
}
}
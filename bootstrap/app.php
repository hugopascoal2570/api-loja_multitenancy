<?php

use App\Http\Middleware\ACLMiddleware;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\PlanFeature;
use App\Http\Middleware\PlatformSuperAdmin;
use App\Http\Middleware\SuperAdmin;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\TokenFromQuery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Log;

/**
 * Parseia erros de entrada duplicada para extrair mensagem amigável
 */
function parseDuplicateEntryError(QueryException $e): string
{
    $message = $e->getMessage();

    $fieldMappings = [
        'products_slug_unique'        => 'Já existe um produto com este nome. Por favor, escolha um nome diferente.',
        'products_name_unique'        => 'Já existe um produto com este nome.',
        'categories_slug_unique'      => 'Já existe uma categoria com este nome.',
        'categories_name_unique'      => 'Já existe uma categoria com este nome.',
        'users_email_unique'          => 'Este e-mail já está cadastrado.',
        'users_cpf_unique'            => 'Este CPF já está cadastrado.',
        'coupons_code_unique'         => 'Este código de cupom já existe.',
        'product_variants_sku_unique' => 'Já existe uma variante com este SKU.',
        'banners_slug_unique'         => 'Já existe um banner com este identificador.',
        'permissions_name_unique'     => 'Já existe uma permissão com este nome.',
        'seamstresses_cpf_unique'     => 'Já existe uma costureira com este CPF.',
        'seamstresses_phone_unique'   => 'Já existe uma costureira com este telefone.',
        'cuts_cut_number_unique'      => 'Já existe um corte com este número.',
    ];

    foreach ($fieldMappings as $key => $friendlyMessage) {
        if (str_contains($message, $key)) {
            return $friendlyMessage;
        }
    }

    return 'Este registro já existe no sistema.';
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Aliases de middleware
        $middleware->alias([
            'tenant'              => TenantMiddleware::class,
            'acl'                 => ACLMiddleware::class,
            'admin'               => CheckAdmin::class,
            'superadmin'          => SuperAdmin::class,
            'token.query'         => TokenFromQuery::class,
            'platform.superadmin' => PlatformSuperAdmin::class,
            'plan.feature'        => PlanFeature::class,
        ]);

        // TokenFromQuery precisa rodar antes do auth:sanctum (Authenticate tem prioridade alta por padrão)
        $middleware->prependToPriorityList(
            TokenFromQuery::class,
            \Illuminate\Auth\Middleware\Authenticate::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handler para erros de banco de dados (apenas em requisições API)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errorCode = $e->errorInfo[1] ?? null;

                $message = match ($errorCode) {
                    1062 => parseDuplicateEntryError($e),
                    1451 => 'Este registro não pode ser excluído pois está sendo usado em outro lugar.',
                    1452 => 'O registro relacionado não foi encontrado.',
                    1048 => 'Um campo obrigatório não foi preenchido.',
                    1364 => 'Um campo obrigatório não foi preenchido.',
                    1406 => 'O texto informado é muito longo para o campo.',
                    1265 => 'O valor informado não é válido para este campo.',
                    1366 => 'O valor informado contém caracteres inválidos.',
                    default => 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente.',
                };

                // Log do erro real para debug
                Log::error('Database Error', [
                    'code' => $errorCode,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => $message,
                    'error_code' => 'DATABASE_ERROR',
                ], 422);
            }
        });

        // Handler para Model não encontrado
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                $modelNames = [
                    'Product' => 'Produto',
                    'ProductVariant' => 'Variante do produto',
                    'Category' => 'Categoria',
                    'User' => 'Usuário',
                    'Order' => 'Pedido',
                    'Cart' => 'Carrinho',
                    'Banner' => 'Banner',
                    'Coupon' => 'Cupom',
                    'Cut' => 'Corte',
                    'Seamstress' => 'Costureira',
                    'FabricRoll' => 'Rolo de tecido',
                    'CutProduction' => 'Produção',
                    'SeamstressAssignment' => 'Distribuição',
                    'Permission' => 'Permissão',
                    'Setting' => 'Configuração',
                    'InventoryMovement' => 'Movimentação de estoque',
                ];

                $modelName = $modelNames[$model] ?? $model;

                return response()->json([
                    'message' => "{$modelName} não encontrado(a).",
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // Handler para rota não encontrada
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Recurso não encontrado.',
                    'error_code' => 'ROUTE_NOT_FOUND',
                ], 404);
            }
        });
    })
    ->create();


<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
                            {--remove : Remove permissões de rotas que não existem mais}
                            {--update-labels : Atualiza descriptions de todas as permissões com os labels definidos}';

    protected $description = 'Sincroniza permissões com as rotas do grupo admin (acl middleware)';

    public function handle(): void
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            return in_array('acl', $route->gatherMiddleware())
                && $route->getName();
        });

        $routeNames = $routes->map(fn($r) => $r->getName())->unique()->sort()->values();

        $existing = Permission::pluck('name')->flip();

        $created = 0;
        foreach ($routeNames as $name) {
            if (!$existing->has($name)) {
                Permission::create([
                    'name'        => $name,
                    'description' => $this->makeLabel($name),
                ]);
                $this->line("  <fg=green>+</> {$name}");
                $created++;
            }
        }

        if ($created === 0) {
            $this->line('  Nenhuma permissão nova encontrada.');
        } else {
            $this->info("{$created} permissão(ões) criada(s).");
        }

        // Opção --update-labels: atualiza description de todas as permissões
        if ($this->option('update-labels')) {
            $updated = 0;
            Permission::all()->each(function ($permission) use (&$updated) {
                $label = $this->makeLabel($permission->name);

                // Só atualiza se tiver label mapeado E for diferente do atual
                if ($label !== $permission->name && $permission->description !== $label) {
                    $this->line("  <fg=yellow>~</> {$permission->name}: \"{$permission->description}\" → \"{$label}\"");
                    $permission->update(['description' => $label]);
                    $updated++;
                }
            });

            if ($updated > 0) {
                $this->info("{$updated} label(s) atualizado(s).");
            } else {
                $this->line('  Todos os labels já estão atualizados.');
            }
        }

        // Opção --remove: apaga permissões cujas rotas não existem mais
        if ($this->option('remove')) {
            $removed = 0;
            $routeNamesFlip = $routeNames->flip();

            Permission::all()->each(function ($permission) use ($routeNamesFlip, &$removed) {
                if (!$routeNamesFlip->has($permission->name)) {
                    $this->line("  <fg=red>-</> {$permission->name}");
                    $permission->delete();
                    $removed++;
                }
            });

            if ($removed > 0) {
                $this->warn("{$removed} permissão(ões) removida(s) (rotas inexistentes).");
            }
        }
    }

    private function makeLabel(string $routeName): string
    {
        $labels = [
            // Auth
            'auth.login'                         => 'Login',
            'auth.logout'                        => 'Logout',
            'auth.me'                            => 'Ver perfil autenticado',
            'auth.register'                      => 'Registro de usuário',
            // Account
            'account.profile'                    => 'Ver perfil',
            'account.update'                     => 'Atualizar perfil',
            'account.orders'                     => 'Listar meus pedidos',
            'account.orders.details'             => 'Ver detalhes do pedido',
            'account.orders.reorder'             => 'Recomprar pedido',
            // Cart
            'cart.index'                         => 'Ver carrinho',
            'cart.add'                           => 'Adicionar ao carrinho',
            'cart.remove'                        => 'Remover do carrinho',
            'cart.clear'                         => 'Limpar carrinho',
            'cart.sync'                          => 'Sincronizar carrinho',
            'cart.token'                         => 'Token do carrinho',
            // Checkout / Pagamento
            'checkout.pix.create'                => 'Criar pagamento PIX',
            'payment.status'                     => 'Status do pagamento',
            'mercadopago.webhook'                => 'Webhook MercadoPago',
            // Cupons (públicos)
            'coupons.validate'                   => 'Validar cupom',
            // Produtos (públicos)
            'products.public'                    => 'Listar produtos públicos',
            'products.promotions'                => 'Listar promoções',
            'home.product.show'                  => 'Ver produto',
            'home.index'                         => 'Home',
            // Categorias (públicas)
            'categories.public'                  => 'Listar categorias públicas',
            // Banners (públicos)
            'banners.active'                     => 'Listar banners ativos',
            // Configurações de entrega (públicas)
            'delivery-settings.show'             => 'Ver configurações de entrega',
            'delivery-settings.check'            => 'Verificar horário de entrega',
            // Senha
            'password.forgot'                    => 'Esqueci minha senha',
            'password.reset'                     => 'Redefinir senha',
            // Dashboard
            'admin.dashboard'                    => 'Visualizar dashboard',
            // Pedidos
            'admin.orders.index'                 => 'Listar pedidos',
            'admin.orders.show'                  => 'Ver pedido',
            'admin.orders.analytics'             => 'Analytics de pedidos',
            'admin.orders.export'                => 'Exportar pedidos',
            'admin.orders.print'                 => 'Imprimir pedido',
            'admin.orders.updateStatus'          => 'Atualizar status do pedido',
            'admin.orders.cancel'                => 'Cancelar pedido',
            // Produtos
            'products.index'                     => 'Listar produtos',
            'products.store'                     => 'Criar produto',
            'products.show'                      => 'Ver produto',
            'products.update'                    => 'Editar produto',
            'products.destroy'                   => 'Excluir produto',
            'products.duplicate'                 => 'Duplicar produto',
            'products.variants.index'            => 'Listar variantes',
            'products.variants.store'            => 'Criar variante',
            'products.variants.update'           => 'Editar variante',
            'products.variants.destroy'          => 'Excluir variante',
            'products.kits.index'                => 'Listar kits do produto',
            'products.kits.store'                => 'Criar kit do produto',
            'kits.update'                        => 'Editar kit',
            'kits.destroy'                       => 'Excluir kit',
            'products.measurements.index'        => 'Listar tabela de medidas',
            'products.measurements.store'        => 'Criar tabela de medidas',
            'products.measurements.bulk'         => 'Criar medidas em lote',
            'products.measurements.image.upload' => 'Upload imagem de medidas',
            'products.measurements.image.delete' => 'Excluir imagem de medidas',
            'measurements.update'                => 'Editar medida',
            'measurements.destroy'               => 'Excluir medida',
            // Categorias
            'categories.index'                   => 'Listar categorias',
            'categories.store'                   => 'Criar categoria',
            'categories.show'                    => 'Ver categoria',
            'categories.update'                  => 'Editar categoria',
            'categories.destroy'                 => 'Excluir categoria',
            // Banners
            'banners.index'                      => 'Listar banners',
            'banners.store'                      => 'Criar banner',
            'banners.show'                       => 'Ver banner',
            'banners.update'                     => 'Editar banner',
            'banners.destroy'                    => 'Excluir banner',
            // Configurações
            'settings.index'                     => 'Listar configurações',
            'settings.store'                     => 'Criar configuração',
            'settings.show'                      => 'Ver configuração',
            'settings.update'                    => 'Editar configuração',
            'settings.destroy'                   => 'Excluir configuração',
            'delivery-settings.update'           => 'Atualizar configurações de entrega',
            // Cupons
            'coupons.index'                      => 'Listar cupons',
            'coupons.store'                      => 'Criar cupom',
            'coupons.show'                       => 'Ver cupom',
            'coupons.update'                     => 'Editar cupom',
            'coupons.destroy'                    => 'Excluir cupom',
            // Permissões
            'permissions.index'                  => 'Listar permissões',
            'permissions.store'                  => 'Criar permissão',
            'permissions.show'                   => 'Ver permissão',
            'permissions.update'                 => 'Editar permissão',
            'permissions.destroy'                => 'Excluir permissão',
            // Usuários
            'users.index'                        => 'Listar usuários',
            'users.store'                        => 'Criar usuário',
            'users.show'                         => 'Ver usuário',
            'users.update'                       => 'Editar usuário',
            'users.destroy'                      => 'Excluir usuário',
            'users.search'                       => 'Buscar usuários',
            'users.make-admin'                   => 'Tornar usuário admin',
            'users.revoke-admin'                 => 'Revogar admin do usuário',
            'users.permissions'                  => 'Ver permissões do usuário',
            'users.permissions.sync'             => 'Sincronizar permissões do usuário',
            // Estoque
            'inventory.all'                      => 'Regularização de estoque',
            'inventory.bulk-set'                 => 'Regularização em massa de estoque',
            'inventory.product'                  => 'Ver estoque do produto',
            'inventory.variant'                  => 'Ver estoque da variante',
            'inventory.variant.add'              => 'Adicionar estoque',
            'inventory.variant.remove'           => 'Remover estoque',
            'inventory.variant.set'              => 'Definir estoque',
            // Movimentações de estoque
            'inventory-movements.history'        => 'Histórico de movimentações',
            'inventory-movements.summary'        => 'Resumo de movimentações',
            'inventory-movements.variant'        => 'Movimentações da variante',
            'inventory-movements.product'        => 'Movimentações do produto',
            'inventory-movements.order'          => 'Movimentações do pedido',
            'inventory-movements.variant.summary'=> 'Resumo de movimentações da variante',
            'inventory-movements.revert'         => 'Reverter movimentação de estoque',
            // Envios
            'admin.shipping.purchase-label'      => 'Gerar etiqueta de envio',
            'admin.shipping.print-label'         => 'Imprimir etiqueta de envio',
            'admin.shipping.tracking'            => 'Rastrear envio',
            'admin.shipping.cancel-label'        => 'Cancelar etiqueta de envio',
            'admin.shipping.reverse-label'       => 'Estornar etiqueta de envio',
            // Vendas de balcão
            'admin.counter-sales.store'          => 'Registrar venda de balcão',
            'admin.counter-sales.index'          => 'Listar vendas de balcão',
            'admin.counter-sales.show'           => 'Ver venda de balcão',
            // Cortes
            'cuts.index'                         => 'Listar cortes',
            'cuts.store'                         => 'Criar corte',
            'cuts.show'                          => 'Ver corte',
            'cuts.update'                        => 'Editar corte',
            'cuts.destroy'                       => 'Excluir corte',
            'cuts.summary'                       => 'Resumo de cortes',
            'cuts.status'                        => 'Atualizar status do corte',
            'cuts.fabric-rolls.index'            => 'Listar rolos de tecido',
            'cuts.fabric-rolls.store'            => 'Criar rolo de tecido',
            'cuts.productions.index'             => 'Listar produções',
            'cuts.productions.store'             => 'Criar produção',
            'cuts.productions.batch'             => 'Criar produções em lote',
            'fabric-rolls.show'                  => 'Ver rolo de tecido',
            'fabric-rolls.update'                => 'Editar rolo de tecido',
            'fabric-rolls.destroy'               => 'Excluir rolo de tecido',
            'productions.show'                   => 'Ver produção',
            'productions.update'                 => 'Editar produção',
            'productions.destroy'                => 'Excluir produção',
            'productions.link-product'           => 'Vincular produto à produção',
            'productions.assignments'            => 'Ver distribuições da produção',
            // Costureiras
            'seamstresses.index'                 => 'Listar costureiras',
            'seamstresses.all'                   => 'Listar todas as costureiras',
            'seamstresses.store'                 => 'Cadastrar costureira',
            'seamstresses.show'                  => 'Ver costureira',
            'seamstresses.update'                => 'Editar costureira',
            'seamstresses.destroy'               => 'Excluir costureira',
            'seamstresses.performance'           => 'Ver desempenho da costureira',
            'seamstresses.costs.index'           => 'Ver custos da costureira',
            'seamstresses.costs.store'           => 'Registrar custo da costureira',
            'seamstresses.assignments'           => 'Ver distribuições da costureira',
            'seamstress-costs.update'            => 'Editar custo da costureira',
            'seamstress-costs.destroy'           => 'Excluir custo da costureira',
            'assignments.index'                  => 'Listar distribuições',
            'assignments.store'                  => 'Criar distribuição',
            'assignments.show'                   => 'Ver distribuição',
            'assignments.return'                 => 'Registrar retorno de distribuição',
            'assignments.destroy'                => 'Excluir distribuição',
            // Newsletter
            'admin.newsletters.index'            => 'Listar newsletters',
            'admin.newsletters.store'            => 'Criar newsletter',
            'admin.newsletters.show'             => 'Ver newsletter',
            'admin.newsletters.update'           => 'Editar newsletter',
            'admin.newsletters.destroy'          => 'Excluir newsletter',
            'admin.newsletters.send'             => 'Enviar newsletter',
            'admin.newsletters.schedule'         => 'Agendar newsletter',
            'admin.newsletters.upload-image'     => 'Upload de imagem de newsletter',
            'admin.newsletter-subscribers.index' => 'Listar inscritos na newsletter',
            'admin.newsletter-subscribers.destroy' => 'Remover inscrito da newsletter',
            // Notificações
            'admin.notifications.index'          => 'Listar notificações',
            'admin.notifications.unread'         => 'Ver notificações não lidas',
            'admin.notifications.read-all'       => 'Marcar todas como lidas',
            'admin.notifications.read'           => 'Marcar notificação como lida',
        ];

        return $labels[$routeName] ?? $routeName;
    }
}

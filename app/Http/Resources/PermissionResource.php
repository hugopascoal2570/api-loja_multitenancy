<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $this->description ?? $this->name,
            'group'       => $this->resolveGroup($this->name),
        ];
    }

    private function resolveGroup(string $name): string
    {
        $groups = [
            'admin.dashboard'           => 'Dashboard',
            'admin.orders'              => 'Pedidos',
            'admin.shipping'            => 'Envios',
            'admin.counter-sales'       => 'Vendas de Balcão',
            'admin.newsletters'         => 'Newsletter',
            'admin.newsletter-sub'      => 'Newsletter',
            'admin.notifications'       => 'Notificações',
            'permissions'               => 'Permissões',
            'users'                     => 'Usuários',
            'products'                  => 'Produtos',
            'kits'                      => 'Produtos',
            'measurements'              => 'Produtos',
            'categories'                => 'Categorias',
            'banners'                   => 'Banners',
            'settings'                  => 'Configurações',
            'delivery-settings'         => 'Configurações',
            'inventory-movements'       => 'Movimentações de Estoque',
            'inventory'                 => 'Estoque',
            'coupons'                   => 'Cupons',
            'cuts'                      => 'Produção',
            'fabric-rolls'              => 'Produção',
            'productions'               => 'Produção',
            'seamstresses'              => 'Costureiras',
            'seamstress-costs'          => 'Costureiras',
            'assignments'               => 'Costureiras',
        ];

        foreach ($groups as $prefix => $group) {
            if (str_starts_with($name, $prefix)) {
                return $group;
            }
        }

        return 'Outros';
    }
}
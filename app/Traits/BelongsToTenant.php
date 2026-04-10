<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;

/**
 * Aplica isolamento automático de tenant em todos os modelos que usam este trait.
 *
 * Como usar:
 *   class Product extends Model {
 *       use BelongsToTenant;
 *   }
 *
 * Efeitos:
 *   - Toda query automáticamente filtra por tenant_id do tenant corrente
 *   - Todo novo registro recebe tenant_id preenchido automaticamente no creating
 *
 * ATENÇÃO: só ativar nos modelos após a Fase 3 (banco com tenant_id preenchido).
 *          Por enquanto este trait existe mas NÃO está aplicado em nenhum model.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Aplica o Global Scope em todas as queries deste model
        static::addGlobalScope(new TenantScope());

        // Preenche tenant_id automaticamente ao criar um novo registro
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->bound('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}

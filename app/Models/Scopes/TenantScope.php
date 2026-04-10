<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope que filtra todas as queries pelo tenant corrente.
 * Injetado automaticamente pelo trait BelongsToTenant.
 *
 * Será ativado na Fase 4 da migração para multi-tenancy.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenant')) {
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', app('tenant')->id);
    }
}

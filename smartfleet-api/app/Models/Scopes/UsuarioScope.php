<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * UsuarioScope — filtra automáticamente todos los queries
 * por el usuario autenticado (fk_usuario = auth user id).
 *
 * Los ADMINs ven todo (sin filtro).
 * Cualquier otro rol solo ve sus propios registros.
 */
class UsuarioScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth('api')->user();

        // Sin sesión (rutas públicas) o ADMIN → sin filtro
        if (!$user) {
            return;
        }

        $rol = optional($user->role)->nombre;

        if ($rol === 'ADMIN') {
            return; // El admin ve todo
        }

        // Cualquier otro rol solo ve lo suyo
        $builder->where($model->getTable() . '.fk_usuario', $user->getKey());
    }
}
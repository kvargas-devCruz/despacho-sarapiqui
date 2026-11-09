<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Permisos de usuarios: ver config/permissions.php, área "users" */
class UserPolicy extends RolePolicy
{
    protected string $area = 'users';

    /** Nadie se borra a sí mismo; así siempre queda al menos un administrador */
    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record) && ! $record->is($user);
    }
}

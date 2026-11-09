<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todas las policies del sistema.
 *
 * Cada policy hija solo indica su área de config/permissions.php y aquí se
 * traducen las acciones de Laravel y Filament (viewAny, update, forceDelete,
 * reorder...) a las cuatro acciones de la matriz: view, create, update y delete.
 * Así una pantalla, un botón y una URL directa siempre responden lo mismo.
 */
abstract class RolePolicy
{
    /** Clave del área en config/permissions.php */
    protected string $area;

    protected function allows(User $user, string $action): bool
    {
        return Permissions::allows($this->area, $action, $user);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, 'update');
    }

    public function replicate(User $user, Model $record): bool
    {
        return $this->allows($user, 'create');
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    /** Borrar definitivamente no se permite desde el panel: los registros se conservan */
    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

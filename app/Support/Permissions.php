<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Reglas de acceso del despacho en un solo lugar (ver config/permissions.php).
 */
class Permissions
{
    /** ¿El rol del usuario puede hacer esta acción en esta área de la matriz? */
    public static function allows(string $area, string $action, ?User $user = null): bool
    {
        /** @var User|null $user */
        $user ??= Auth::user();

        if ($user === null || $user->role === null) {
            return false;
        }

        return in_array($user->role->value, config("permissions.{$area}.{$action}", []), true);
    }

    public static function canViewCosts(): bool
    {
        return self::allows('costs', 'view');
    }

    public static function canShareTracking(): bool
    {
        return self::allows('tracking', 'create');
    }
}

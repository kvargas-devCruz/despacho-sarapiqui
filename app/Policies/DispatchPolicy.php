<?php

namespace App\Policies;

/** Permisos de Dispatch: ver config/permissions.php, área "dispatches" */
class DispatchPolicy extends RolePolicy
{
    protected string $area = 'dispatches';
}

<?php

namespace App\Policies;

/** Permisos de DispatchEvent: ver config/permissions.php, área "dispatch_events" */
class DispatchEventPolicy extends RolePolicy
{
    protected string $area = 'dispatch_events';
}

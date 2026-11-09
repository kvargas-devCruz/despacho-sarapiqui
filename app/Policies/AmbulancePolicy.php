<?php

namespace App\Policies;

/** Permisos de Ambulance: ver config/permissions.php, área "ambulances" */
class AmbulancePolicy extends RolePolicy
{
    protected string $area = 'ambulances';
}

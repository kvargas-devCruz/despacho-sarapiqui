<?php

namespace App\Policies;

/** Permisos de AmbulanceLocation: ver config/permissions.php, área "ambulance_locations" */
class AmbulanceLocationPolicy extends RolePolicy
{
    protected string $area = 'ambulance_locations';
}

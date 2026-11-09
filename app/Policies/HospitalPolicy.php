<?php

namespace App\Policies;

/** Permisos de Hospital: ver config/permissions.php, área "hospitals" */
class HospitalPolicy extends RolePolicy
{
    protected string $area = 'hospitals';
}

<?php

namespace App\Policies;

/** Permisos de Patient: ver config/permissions.php, área "patients" */
class PatientPolicy extends RolePolicy
{
    protected string $area = 'patients';
}

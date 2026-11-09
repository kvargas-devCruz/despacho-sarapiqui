<?php

namespace App\Policies;

/** Permisos de ScheduledTransfer: ver config/permissions.php, área "scheduled_transfers" */
class ScheduledTransferPolicy extends RolePolicy
{
    protected string $area = 'scheduled_transfers';
}

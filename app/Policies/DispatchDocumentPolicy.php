<?php

namespace App\Policies;

/** Permisos de DispatchDocument: ver config/permissions.php, área "dispatch_documents" */
class DispatchDocumentPolicy extends RolePolicy
{
    protected string $area = 'dispatch_documents';
}

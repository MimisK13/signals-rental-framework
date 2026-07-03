<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class InvoicePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'invoices.view';
    }

    protected function managePermission(): string
    {
        return 'invoices.create';
    }

    protected function createPermission(): string
    {
        return 'invoices.create';
    }

    protected function editPermission(): string
    {
        return 'invoices.edit';
    }

    protected function deletePermission(): string
    {
        return 'invoices.delete';
    }
}

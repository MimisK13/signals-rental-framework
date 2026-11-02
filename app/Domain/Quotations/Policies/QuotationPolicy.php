<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Policies;

use App\Domain\Quotations\Models\Quotation;
use App\Models\User;

final class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.edit');
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.delete');
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.send');
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.accept');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire;

use App\Domain\Administration\Services\PermissionRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Permissions')]
final class Permissions extends Component
{
    /** @return array<string, mixed> */
    public function with(): array
    {
        $registry = app(PermissionRegistry::class);
        $roles = Role::with('permissions')->orderBy('sort_order')->get();

        return [
            'permissionGroups' => $registry->grouped(),
            'roles' => $roles,
        ];
    }

    public function render(): View
    {
        return view('administration::permissions', $this->with());
    }
}

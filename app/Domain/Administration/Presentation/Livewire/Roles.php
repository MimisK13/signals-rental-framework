<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire;

use App\Domain\Administration\Actions\DeleteRole;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Roles')]
final class Roles extends Component
{
    public ?int $confirmingDeletion = null;

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'roles' => Role::query()
                ->where('name', '!=', 'Owner')
                ->withCount('users')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function deleteRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        (new DeleteRole)($role);
        $this->confirmingDeletion = null;
        $this->dispatch('role-deleted');
    }

    public function render(): View
    {
        return view('administration::roles', $this->with());
    }
}

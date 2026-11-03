<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire;

use App\Domain\Administration\Actions\CreateRole;
use App\Domain\Administration\Actions\UpdateRole;
use App\Domain\Administration\Models\User;
use App\Domain\Administration\Services\PermissionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Role')]
final class RoleForm extends Component
{
    public ?int $roleId = null;

    public string $roleName = '';

    public string $roleDescription = '';

    /** @var list<string> */
    public array $selectedPermissions = [];

    /** @var list<int> */
    public array $selectedUsers = [];

    public bool $isSystem = false;

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->roleId = $role->id;
            $this->roleName = $role->name;
            $this->roleDescription = $role->description ?? '';
            $this->selectedPermissions = $role->permissions->pluck('name')->all();
            $this->selectedUsers = User::role($role->name)->pluck('id')->all();
            $this->isSystem = (bool) $role->getAttribute('is_system');
        }
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $registry = app(PermissionRegistry::class);

        return [
            'permissionGroups' => $registry->grouped(),
            'allUsers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_owner']),
            'isEditing' => $this->roleId !== null,
        ];
    }

    public function save(): void
    {
        $registry = app(PermissionRegistry::class);
        $validPermissions = array_keys($registry->all());

        $this->validate([
            'roleName' => ['required', 'string', 'max:255'],
            'roleDescription' => ['nullable', 'string', 'max:1000'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::in($validPermissions)],
            'selectedUsers' => ['array'],
            'selectedUsers.*' => ['integer', 'exists:users,id'],
        ]);

        if ($this->roleId) {
            $role = Role::findOrFail($this->roleId);
            (new UpdateRole)($role, [
                'name' => $this->roleName,
                'description' => $this->roleDescription,
                'permissions' => $this->selectedPermissions,
            ]);
        } else {
            $role = (new CreateRole)([
                'name' => $this->roleName,
                'description' => $this->roleDescription,
                'permissions' => $this->selectedPermissions,
            ]);
        }

        $this->syncRoleUsers($role);

        $this->redirect(route('admin.settings.roles'), navigate: true);
    }

    public function toggleGroupPermissions(string $group): void
    {
        $registry = app(PermissionRegistry::class);
        $grouped = $registry->grouped();
        $groupPermissions = array_keys($grouped[$group] ?? []);

        $allSelected = count(array_intersect($groupPermissions, $this->selectedPermissions)) === count($groupPermissions);

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    private function syncRoleUsers(Role $role): void
    {
        $currentUserIds = User::role($role->name)->pluck('id')->all();
        $newUserIds = $this->selectedUsers;

        $toAssign = array_diff($newUserIds, $currentUserIds);
        $toRemove = array_diff($currentUserIds, $newUserIds);

        foreach ($toAssign as $userId) {
            $user = User::find($userId);
            $user?->assignRole($role);
        }

        foreach ($toRemove as $userId) {
            $user = User::find($userId);
            $user?->removeRole($role);
        }
    }

    public function render(): View
    {
        return view('administration::role-form', $this->with());
    }
}

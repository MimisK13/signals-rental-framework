<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Security')]
final class Security extends Component
{
    public int $passwordMinLength = 8;

    public bool $passwordRequireUppercase = false;

    public bool $passwordRequireNumber = false;

    public bool $passwordRequireSpecial = false;

    public int $sessionTimeout = 120;

    public int $maxLoginAttempts = 5;

    public int $lockoutDuration = 15;

    public bool $require2faAdmin = false;

    public bool $require2faAll = false;

    public bool $magicLinkEnabled = false;

    /** @var list<string> */
    public array $ssoEnforcedRoles = [];

    public function mount(): void
    {
        $group = settings()->group('security');

        $this->passwordMinLength = (int) $group['password_min_length'];
        $this->passwordRequireUppercase = (bool) $group['password_require_uppercase'];
        $this->passwordRequireNumber = (bool) $group['password_require_number'];
        $this->passwordRequireSpecial = (bool) $group['password_require_special'];
        $this->sessionTimeout = (int) $group['session_timeout'];
        $this->maxLoginAttempts = (int) $group['max_login_attempts'];
        $this->lockoutDuration = (int) $group['lockout_duration'];
        $this->require2faAdmin = (bool) $group['require_2fa_admin'];
        $this->require2faAll = (bool) $group['require_2fa_all'];
        $this->magicLinkEnabled = (bool) $group['magic_link_enabled'];

        // Drop stale entries whose role was later renamed or deleted, so a stored
        // value that no longer satisfies `exists:roles,name` cannot block saving
        // the entire Security settings form.
        $existingRoleNames = Role::query()->pluck('name')->all();
        $this->ssoEnforcedRoles = array_values(array_intersect(
            array_values((array) $group['sso_enforced_roles']),
            $existingRoleNames,
        ));
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('security');
        $types = $registry->types('security');

        $validated = $this->validate([
            'passwordMinLength' => $rules['password_min_length'],
            'passwordRequireUppercase' => $rules['password_require_uppercase'],
            'passwordRequireNumber' => $rules['password_require_number'],
            'passwordRequireSpecial' => $rules['password_require_special'],
            'sessionTimeout' => $rules['session_timeout'],
            'maxLoginAttempts' => $rules['max_login_attempts'],
            'lockoutDuration' => $rules['lockout_duration'],
            'require2faAdmin' => $rules['require_2fa_admin'],
            'require2faAll' => $rules['require_2fa_all'],
            'ssoEnforcedRoles' => $rules['sso_enforced_roles'],
            'ssoEnforcedRoles.*' => $rules['sso_enforced_roles.*'],
            'magicLinkEnabled' => $rules['magic_link_enabled'],
        ]);

        settings()->setMany([
            'security.password_min_length' => ['value' => $validated['passwordMinLength'], 'type' => $types['password_min_length']],
            'security.password_require_uppercase' => ['value' => $validated['passwordRequireUppercase'], 'type' => $types['password_require_uppercase']],
            'security.password_require_number' => ['value' => $validated['passwordRequireNumber'], 'type' => $types['password_require_number']],
            'security.password_require_special' => ['value' => $validated['passwordRequireSpecial'], 'type' => $types['password_require_special']],
            'security.session_timeout' => ['value' => $validated['sessionTimeout'], 'type' => $types['session_timeout']],
            'security.max_login_attempts' => ['value' => $validated['maxLoginAttempts'], 'type' => $types['max_login_attempts']],
            'security.lockout_duration' => ['value' => $validated['lockoutDuration'], 'type' => $types['lockout_duration']],
            'security.require_2fa_admin' => ['value' => $validated['require2faAdmin'], 'type' => $types['require_2fa_admin']],
            'security.require_2fa_all' => ['value' => $validated['require2faAll'], 'type' => $types['require_2fa_all']],
            'security.sso_enforced_roles' => ['value' => array_values($validated['ssoEnforcedRoles']), 'type' => $types['sso_enforced_roles']],
            'security.magic_link_enabled' => ['value' => $validated['magicLinkEnabled'], 'type' => $types['magic_link_enabled']],
        ]);

        $this->dispatch('security-settings-saved');
    }

    /** @return array{enforceableRoles: Collection<int, Role>} */
    public function with(): array
    {
        return [
            // Owner is a membership flag, not a Spatie role, and is always exempt from SSO enforcement.
            'enforceableRoles' => Role::query()
                ->where('name', '!=', 'Owner')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function render(): View
    {
        return view('settings::admin.security');
    }
}

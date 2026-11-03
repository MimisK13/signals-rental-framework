<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Communications\Models\CommunicationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function administrationUserIdentityMigration(string $sequence): object
{
    $paths = glob(app_path("Domain/Administration/Database/Migrations/2026_11_03_{$sequence}_*.php"));

    if (! is_array($paths) || count($paths) !== 1) {
        throw new RuntimeException("Expected one Administration identity migration for sequence {$sequence}.");
    }

    return require $paths[0];
}

it('canonicalizes persisted User model identities without legacy mappings', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Identity Test Role', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'identity.test', 'guard_name' => 'web']);
    $user->assignRole($role);
    $user->givePermissionTo($permission);
    $token = $user->createToken('identity-test')->accessToken;
    $actionLog = ActionLog::factory()->create(['auditable_type' => User::class, 'auditable_id' => $user->id]);
    $communication = CommunicationLog::factory()->create();
    $notificationId = (string) Str::uuid();
    $old = 'App\\Models\\User';

    DB::table('personal_access_tokens')->where('id', $token->id)->update(['tokenable_type' => $old]);
    DB::table('model_has_roles')->where('model_id', $user->id)->update(['model_type' => $old]);
    DB::table('model_has_permissions')->where('model_id', $user->id)->update(['model_type' => $old]);
    DB::table('action_logs')->where('id', $actionLog->id)->update(['auditable_type' => $old]);
    DB::table('communication_log')->where('id', $communication->id)->update([
        'notifiable_type' => $old,
        'notifiable_id' => $user->id,
        'subject_type' => $old,
        'subject_id' => $user->id,
    ]);
    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'identity-test',
        'notifiable_type' => $old,
        'notifiable_id' => $user->id,
        'data' => json_encode([], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['000031', '000032', '000033', '000034', '000035', '000036'] as $sequence) {
        administrationUserIdentityMigration($sequence)->up();
    }

    expect(DB::table('personal_access_tokens')->where('id', $token->id)->value('tokenable_type'))->toBe(User::class)
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe(User::class)
        ->and(DB::table('model_has_permissions')->where('model_id', $user->id)->value('model_type'))->toBe(User::class)
        ->and(DB::table('notifications')->where('id', $notificationId)->value('notifiable_type'))->toBe(User::class)
        ->and(DB::table('action_logs')->where('id', $actionLog->id)->value('auditable_type'))->toBe(User::class)
        ->and(DB::table('communication_log')->where('id', $communication->id)->value('notifiable_type'))->toBe(User::class)
        ->and(DB::table('communication_log')->where('id', $communication->id)->value('subject_type'))->toBe(User::class);
});

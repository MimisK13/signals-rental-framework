<?php

use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\NotificationChannelResolver;

beforeEach(function () {
    $this->resolver = app(NotificationChannelResolver::class);
});

/**
 * @return list<array{list<string>, list<string>|null, bool, list<string>|null, bool, list<string>}>
 */
function channelResolverDataset(): array
{
    return [
        [['in_app', 'email'], null, true, null, false, ['in_app', 'email']],
        [['in_app', 'email'], ['in_app'], true, null, false, ['in_app']],
        [['in_app', 'email'], ['in_app', 'email', 'sms'], true, null, false, ['in_app', 'email']],
        [['in_app', 'email'], ['in_app', 'email'], false, null, false, []],
        [['in_app', 'email'], ['in_app', 'email'], true, ['email'], false, ['email']],
        [['in_app', 'email'], ['in_app', 'email'], true, ['in_app', 'email', 'slack'], false, ['in_app', 'email']],
        [['in_app', 'email'], ['in_app', 'email'], true, ['email'], true, []],
        [['database', 'mail'], ['database'], true, null, false, ['in_app']],
        [['in_app', 'email', 'sms'], ['in_app', 'email'], true, ['email'], false, ['email']],
    ];
}

describe('NotificationChannelResolver three-layer narrowing', function () {
    it('narrows channels per layer without ever expanding', function (
        array $defaults,
        ?array $tenant,
        bool $tenantEnabled,
        ?array $member,
        bool $muted,
        array $expected,
    ) {
        $type = NotificationType::factory()->create([
            'default_channels' => $defaults,
            'available_channels' => ['in_app', 'email', 'sms', 'slack'],
        ]);

        if ($tenant !== null) {
            NotificationSetting::factory()->create([
                'notification_type_id' => $type->id,
                'channels' => $tenant,
                'is_enabled' => $tenantEnabled,
            ]);
        }

        $recipient = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $recipient->id]);

        if ($member !== null || $muted) {
            NotificationPreference::factory()->create([
                'user_id' => $user->id,
                'notification_type_id' => $type->id,
                'channels' => $member,
                'is_muted' => $muted,
            ]);
        }

        expect($this->resolver->resolve($type->fresh(['setting']), $recipient))->toBe($expected);
    })->with(channelResolverDataset());

    it('expands in_app to database and broadcast for Laravel channels', function () {
        expect($this->resolver->toLaravelChannels(['in_app', 'email']))
            ->toBe(['database', 'broadcast', 'mail']);
    });

    it('passes through non-email logical channels unchanged', function () {
        expect($this->resolver->toLaravelChannels(['sms']))
            ->toBe(['sms']);
    });

    it('does not narrow when tenant setting channels are null', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['in_app', 'email'],
        ]);

        NotificationSetting::factory()->create([
            'notification_type_id' => $type->id,
            'channels' => null,
            'is_enabled' => true,
        ]);

        $recipient = Member::factory()->create();

        expect($this->resolver->resolve($type->fresh(['setting']), $recipient))
            ->toBe(['in_app', 'email']);
    });

    it('does not narrow when member preference channels are null', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['in_app', 'email'],
        ]);

        $recipient = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $recipient->id]);

        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type_id' => $type->id,
            'channels' => null,
            'is_muted' => false,
        ]);

        expect($this->resolver->resolve($type->fresh(['setting']), $recipient))
            ->toBe(['in_app', 'email']);
    });
});

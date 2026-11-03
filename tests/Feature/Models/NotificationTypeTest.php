<?php

use App\Domain\Administration\Models\User;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\NotificationType;

describe('effectiveChannels', function () {
    it('returns default channels when no setting exists', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['database', 'mail'],
        ]);

        expect($type->effectiveChannels())->toBe(['database', 'mail']);
    });

    it('returns empty array when setting is disabled', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['database', 'mail'],
        ]);

        NotificationSetting::factory()->disabled()->create([
            'notification_type_id' => $type->id,
        ]);

        // Reload to clear any cached relationship
        $type->refresh();

        expect($type->effectiveChannels())->toBe([]);
    });

    it('returns setting channels when setting overrides channels', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['database', 'mail'],
        ]);

        NotificationSetting::factory()->create([
            'notification_type_id' => $type->id,
            'channels' => ['broadcast'],
            'is_enabled' => true,
        ]);

        $type->refresh();

        expect($type->effectiveChannels())->toBe(['broadcast']);
    });

    it('returns default channels when setting is enabled with null channels', function () {
        $type = NotificationType::factory()->create([
            'default_channels' => ['database'],
        ]);

        NotificationSetting::factory()->create([
            'notification_type_id' => $type->id,
            'channels' => null,
            'is_enabled' => true,
        ]);

        $type->refresh();

        expect($type->effectiveChannels())->toBe(['database']);
    });
});

describe('relationships', function () {
    it('has many preferences', function () {
        $type = NotificationType::factory()->create();
        $user = User::factory()->create();

        NotificationPreference::create([
            'notification_type_id' => $type->id,
            'user_id' => $user->id,
            'channels' => ['database'],
        ]);

        expect($type->preferences)->toHaveCount(1);
    });
});

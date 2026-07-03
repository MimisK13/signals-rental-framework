<?php

use App\Actions\Notifications\LogCommunication;
use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Data\Notifications\TransitionCommunicationLogData;
use App\Models\CommunicationLog;
use App\Models\Member;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Notifications\RecipientResolverRegistry;
use App\Services\Plugins\PluginRegistrar;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

describe('RecipientResolverRegistry core resolvers', function () {
    it('resolves subject_owner from opportunity owned_by', function () {
        $owner = Member::factory()->create();
        $opportunity = Opportunity::factory()->create(['owned_by' => $owner->id]);
        $type = NotificationType::factory()->create();

        $context = new NotificationContextData(
            type: $type,
            subject: $opportunity,
        );

        $recipients = app(RecipientResolverRegistry::class)->resolveAll(
            [['type' => 'subject_owner']],
            $context,
        );

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first()->id)->toBe($owner->id);
    });

    it('resolves specific_member by member_id', function () {
        $member = Member::factory()->create();
        $type = NotificationType::factory()->create();
        $opportunity = Opportunity::factory()->create();

        $context = new NotificationContextData(type: $type, subject: $opportunity);

        $recipients = app(RecipientResolverRegistry::class)->resolveAll(
            [['type' => 'specific_member', 'member_id' => $member->id]],
            $context,
        );

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first()->id)->toBe($member->id);
    });

    it('resolves trigger_actor from context', function () {
        $actor = Member::factory()->create();
        $type = NotificationType::factory()->create();
        $opportunity = Opportunity::factory()->create();

        $context = new NotificationContextData(
            type: $type,
            subject: $opportunity,
            actor: $actor,
        );

        $recipients = app(RecipientResolverRegistry::class)->resolveAll(
            [['type' => 'trigger_actor']],
            $context,
        );

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first()->id)->toBe($actor->id);
    });

    it('resolves contact from opportunity member_id', function () {
        $contact = Member::factory()->contact()->create();
        $opportunity = Opportunity::factory()->create(['member_id' => $contact->id]);
        $type = NotificationType::factory()->create();

        $context = new NotificationContextData(type: $type, subject: $opportunity);

        $recipients = app(RecipientResolverRegistry::class)->resolveAll(
            [['type' => 'contact']],
            $context,
        );

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first()->id)->toBe($contact->id);
    });

    it('resolves role members linked through users', function () {
        $role = Role::create(['name' => 'Ops Lead', 'guard_name' => 'web']);

        $firstMember = Member::factory()->create();
        $secondMember = Member::factory()->create();
        $firstUser = User::factory()->create(['member_id' => $firstMember->id]);
        $secondUser = User::factory()->create(['member_id' => $secondMember->id]);

        $firstUser->assignRole($role);
        $secondUser->assignRole($role);

        $type = NotificationType::factory()->create();
        $opportunity = Opportunity::factory()->create();

        $context = new NotificationContextData(type: $type, subject: $opportunity);

        $recipients = app(RecipientResolverRegistry::class)->resolveAll(
            [['type' => 'role', 'role' => 'Ops Lead']],
            $context,
        );

        expect($recipients)->toHaveCount(2)
            ->and($recipients->pluck('id')->sort()->values()->all())
            ->toBe(collect([$firstMember->id, $secondMember->id])->sort()->values()->all());
    });

    it('deduplicates recipients across multiple rules', function () {
        $member = Member::factory()->create();
        $type = NotificationType::factory()->create();
        $opportunity = Opportunity::factory()->create(['owned_by' => $member->id]);

        $context = new NotificationContextData(type: $type, subject: $opportunity);

        $recipients = app(RecipientResolverRegistry::class)->resolveAll([
            ['type' => 'subject_owner'],
            ['type' => 'specific_member', 'member_id' => $member->id],
        ], $context);

        expect($recipients)->toHaveCount(1);
    });

    it('allows plugins to register additional recipient resolver types', function () {
        $pluginResolver = new class implements RecipientResolver
        {
            public function type(): string
            {
                return 'plugin_account_manager';
            }

            public function resolve(array $rule, NotificationContextData $context): Collection
            {
                return collect([Member::factory()->create()]);
            }
        };

        app(PluginRegistrar::class)->recipientResolver($pluginResolver);

        $registry = app(RecipientResolverRegistry::class);

        expect($registry->has('plugin_account_manager'))->toBeTrue()
            ->and($registry->types())->toContain('plugin_account_manager');
    });
});

describe('LogCommunication status transitions', function () {
    it('transitions queued to sent using config-driven rules', function () {
        $log = CommunicationLog::factory()->create(['status' => 'queued']);

        $updated = app(LogCommunication::class)->transition(
            new TransitionCommunicationLogData(
                communicationLogId: $log->id,
                status: 'sent',
            ),
        );

        expect($updated->status)->toBe('sent')
            ->and($updated->sent_at)->not->toBeNull();
    });

    it('rejects illegal status transitions', function () {
        $log = CommunicationLog::factory()->sent()->create();

        expect(fn () => app(LogCommunication::class)->transition(
            new TransitionCommunicationLogData(
                communicationLogId: $log->id,
                status: 'queued',
            ),
        ))->toThrow(InvalidArgumentException::class);
    });
});

<?php

use App\Enums\OpportunityState;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\Opportunity;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\EventNotificationMapRegistry;

describe('EventNotificationMapRegistry', function () {
    it('resolves core opportunity.created mapping from config', function () {
        $registry = app(EventNotificationMapRegistry::class);

        $entries = $registry->resolve('opportunity.created');

        expect($entries)->toHaveCount(1)
            ->and($registry->notificationTypesForEntry($entries[0]))->toBe(['opportunity.created']);
    });

    it('returns an empty list for unknown audit actions', function () {
        $registry = app(EventNotificationMapRegistry::class);

        expect($registry->resolve('member.updated'))->toBe([]);
    });

    it('merges plugin-registered entries onto core mappings', function () {
        $registry = app(EventNotificationMapRegistry::class);

        $registry->register('opportunity.created', [
            ['type' => 'plugin.extra.alert'],
        ]);

        expect($registry->resolve('opportunity.created'))->toHaveCount(2);
    });

    it('fans out multiple notification types from a single entry', function () {
        $registry = new EventNotificationMapRegistry;

        $registry->register('demo.multi', [
            ['types' => ['alpha.notify', 'beta.notify']],
        ]);

        expect($registry->notificationTypesForEntry($registry->resolve('demo.multi')[0]))
            ->toBe(['alpha.notify', 'beta.notify']);
    });
});

describe('EventNotificationConditionEvaluator', function () {
    it('matches unconditional entries', function () {
        $evaluator = app(EventNotificationConditionEvaluator::class);
        $event = new AuditableEvent(Member::factory()->make(), 'demo.action');

        expect($evaluator->matches(null, $event))->toBeTrue()
            ->and($evaluator->matches([], $event))->toBeTrue();
    });

    it('matches model and new-value conditions for opportunity cancellation', function () {
        $evaluator = app(EventNotificationConditionEvaluator::class);

        $opportunity = Opportunity::factory()->make([
            'state' => OpportunityState::Order->value,
            'status' => 6,
        ]);

        $event = new AuditableEvent(
            model: $opportunity,
            action: 'opportunity.status_changed',
            oldValues: ['status' => 0],
            newValues: ['status' => 6],
        );

        $conditions = [
            ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
            ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 6],
        ];

        expect($evaluator->matches($conditions, $event))->toBeTrue();
    });

    it('rejects entries when a condition does not match', function () {
        $evaluator = app(EventNotificationConditionEvaluator::class);

        $opportunity = Opportunity::factory()->make([
            'state' => OpportunityState::Order->value,
            'status' => 1,
        ]);

        $event = new AuditableEvent(
            model: $opportunity,
            action: 'opportunity.status_changed',
            newValues: ['status' => 1],
        );

        $conditions = [
            ['source' => 'model', 'field' => 'state', 'operator' => 'eq', 'value' => OpportunityState::Order->value],
            ['source' => 'new', 'field' => 'status', 'operator' => 'eq', 'value' => 6],
        ];

        expect($evaluator->matches($conditions, $event))->toBeFalse();
    });

    it('supports in and present operators', function () {
        $evaluator = app(EventNotificationConditionEvaluator::class);

        $opportunity = Opportunity::factory()->make([
            'state' => OpportunityState::Quotation->value,
            'status' => 2,
        ]);

        $event = new AuditableEvent(
            model: $opportunity,
            action: 'opportunity.status_changed',
            newValues: ['status' => 2],
            metadata: ['reason' => 'lost'],
        );

        expect($evaluator->matches([
            ['source' => 'new', 'field' => 'status', 'operator' => 'in', 'value' => [2, 3]],
        ], $event))->toBeTrue()
            ->and($evaluator->matches([
                ['source' => 'metadata', 'field' => 'reason', 'operator' => 'present'],
            ], $event))->toBeTrue();
    });
});

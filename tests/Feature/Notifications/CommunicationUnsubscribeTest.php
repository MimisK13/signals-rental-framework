<?php

use App\Models\Member;
use App\Services\Notifications\CommunicationPreferences;
use App\Services\Notifications\CustomerCommunicationUnsubscribeUrl;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

describe('communication unsubscribe public route', function () {
    it('shows a confirm page with masked email for a valid signed link', function () {
        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'patricia@example.com', 'is_primary' => true]);

        $url = app(CustomerCommunicationUnsubscribeUrl::class)->forMember($member, 'invoice.created');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Unsubscribe from emails', false);
        $response->assertSee('p***@example.com', false);
        $response->assertDontSee('patricia@example.com', false);
        $response->assertDontSee($member->name, false);
    });

    it('opts the member out on confirm and suppresses future sends of that type', function () {
        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'confirm@example.com', 'is_primary' => true]);

        $url = app(CustomerCommunicationUnsubscribeUrl::class)->forMember($member, 'invoice.created');

        $response = $this->post($url);

        $response->assertOk();
        $response->assertSee('You have been unsubscribed', false);

        expect(app(CommunicationPreferences::class)->isOptedOut($member->fresh(), 'invoice.created'))->toBeTrue();
    });

    it('rejects unsigned links with forbidden and no member data', function () {
        $member = Member::factory()->contact()->create();

        $response = $this->get(route('communications.unsubscribe.show', [
            'member' => $member->id,
            'type' => 'invoice.created',
        ]));

        $response->assertForbidden();
        $response->assertDontSee($member->name, false);
    });

    it('rejects tampered signatures with forbidden', function () {
        $member = Member::factory()->contact()->create();
        $url = URL::signedRoute('communications.unsubscribe.show', [
            'member' => $member->id,
            'type' => 'invoice.created',
        ]).'&extra=tampered';

        $this->get($url)->assertForbidden();
    });

    it('returns not found for a missing member without leaking existence details', function () {
        $url = URL::signedRoute('communications.unsubscribe.show', [
            'member' => 999999,
            'type' => 'invoice.created',
        ]);

        $response = $this->get($url);

        $response->assertNotFound();
        $response->assertDontSee('999999', false);
    });

    it('returns not found when the notification type parameter is missing', function () {
        $member = Member::factory()->contact()->create();

        $url = URL::signedRoute('communications.unsubscribe.show', [
            'member' => $member->id,
        ]);

        $this->get($url)->assertNotFound();
    });

    it('rejects expired signed links', function () {
        $member = Member::factory()->contact()->create();

        $url = URL::temporarySignedRoute(
            'communications.unsubscribe.show',
            now()->subMinute(),
            ['member' => $member->id, 'type' => 'invoice.created'],
        );

        $this->get($url)->assertForbidden();
    });

    it('throttles excessive unsubscribe attempts', function () {
        $member = Member::factory()->contact()->create();
        $url = app(CustomerCommunicationUnsubscribeUrl::class)->forMember($member, 'invoice.created');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    });

    it('uses notifications.unsubscribe_url_ttl_days for signed link expiry', function () {
        $member = Member::factory()->contact()->create();
        $service = app(CustomerCommunicationUnsubscribeUrl::class);

        $this->travelTo('2026-01-15 12:00:00');

        settings()->set('notifications.unsubscribe_url_ttl_days', 30);
        $shortTtlUrl = $service->forMember($member, 'invoice.created');

        settings()->set('notifications.unsubscribe_url_ttl_days', 90);
        $longTtlUrl = $service->forMember($member, 'invoice.created');

        parse_str(parse_url($shortTtlUrl, PHP_URL_QUERY) ?? '', $shortQuery);
        parse_str(parse_url($longTtlUrl, PHP_URL_QUERY) ?? '', $longQuery);

        expect((int) $shortQuery['expires'])->toBe(now()->addDays(30)->timestamp)
            ->and((int) $longQuery['expires'])->toBe(now()->addDays(90)->timestamp);
    });
});

describe('CommunicationPreferences', function () {
    it('masks email addresses for public display', function () {
        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'alice.smith@rentals.test', 'is_primary' => true]);

        expect(app(CommunicationPreferences::class)->maskedEmail($member))->toBe('a***@rentals.test');
    });

    it('records opt-out types idempotently', function () {
        $member = Member::factory()->contact()->create();
        $preferences = app(CommunicationPreferences::class);

        $preferences->optOut($member, 'invoice.created');
        $preferences->optOut($member, 'invoice.created');

        expect($preferences->optedOutTypes($member->fresh()))->toBe(['invoice.created']);
    });
});

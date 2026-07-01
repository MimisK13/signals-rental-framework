<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('webhooks.manage');
    $this->actingAs($this->user);
});

it('renders the webhooks page for a user with webhooks.manage', function () {
    $this->get(route('api.webhooks'))
        ->assertOk()
        ->assertSee('Webhooks')
        ->assertSee('Manage outbound subscriptions and inbound webhook sources');
});

it('forbids the webhooks page without webhooks.manage', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('api.webhooks'))
        ->assertForbidden();
});

it('redirects the old admin webhooks route to api webhooks', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('admin.settings.webhooks'))
        ->assertRedirect('/api/webhooks');
});

it('does not show webhooks in the admin sidebar', function () {
    $owner = User::factory()->owner()->create();

    $html = $this->actingAs($owner)
        ->get(route('admin.settings.api'))
        ->assertOk()
        ->getContent();

    expect(preg_match('/s-admin-nav-item[^>]*href="'.preg_quote(route('admin.settings.webhooks'), '/').'"/', $html))
        ->toBe(0);
});

describe('outbound webhooks', function () {
    it('shows empty state when no webhooks exist', function () {
        $this->get(route('api.webhooks'))
            ->assertSee('No webhooks');
    });

    it('lists existing webhooks', function () {
        Webhook::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->get(route('api.webhooks'))
            ->assertOk();

        expect(Webhook::where('user_id', $this->user->id)->count())->toBe(3);
    });

    it('can create a webhook', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['user.created', 'user.updated'])
            ->call('createWebhook')
            ->assertHasNoErrors();

        expect(Webhook::count())->toBe(1);
        expect(Webhook::first()->url)->toBe('https://example.com/webhook');
        expect(Webhook::first()->events)->toBe(['user.created', 'user.updated']);
    });

    it('shows the secret after creation', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook')
            ->assertSet('showOutboundSecret', true)
            ->assertNotSet('plainOutboundSecret', '');
    });

    it('dispatches event on webhook creation', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook')
            ->assertDispatched('webhook-created');
    });

    it('validates webhook creation requires url', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', '')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook')
            ->assertHasErrors(['createUrl']);
    });

    it('validates webhook creation requires events', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', [])
            ->call('createWebhook')
            ->assertHasErrors(['createEvents']);
    });

    it('validates webhook creation requires valid url', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'not-a-url')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook')
            ->assertHasErrors(['createUrl']);
    });

    it('validates webhook creation rejects invalid events', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['invalid.event'])
            ->call('createWebhook')
            ->assertHasErrors(['createEvents.0']);
    });

    it('can edit a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);

        Volt::test('api.webhooks')
            ->call('openEditModal', $webhook->id)
            ->set('editUrl', 'https://new-url.com/hook')
            ->set('editEvents', ['role.created', 'role.deleted'])
            ->call('updateWebhook')
            ->assertHasNoErrors();

        expect($webhook->fresh()->url)->toBe('https://new-url.com/hook');
        expect($webhook->fresh()->events)->toBe(['role.created', 'role.deleted']);
    });

    it('dispatches event on webhook update', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);

        Volt::test('api.webhooks')
            ->call('openEditModal', $webhook->id)
            ->set('editUrl', 'https://new-url.com/hook')
            ->call('updateWebhook')
            ->assertDispatched('webhook-updated');
    });

    it('populates edit modal with current values', function () {
        $webhook = Webhook::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://original.com/hook',
            'events' => ['user.created', 'settings.updated'],
            'is_active' => true,
        ]);

        Volt::test('api.webhooks')
            ->call('openEditModal', $webhook->id)
            ->assertSet('editUrl', 'https://original.com/hook')
            ->assertSet('editEvents', ['user.created', 'settings.updated'])
            ->assertSet('editIsActive', true);
    });

    it('can delete a webhook', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);

        Volt::test('api.webhooks')
            ->call('confirmDelete', $webhook->id)
            ->call('deleteWebhook')
            ->assertHasNoErrors();

        expect(Webhook::count())->toBe(0);
    });

    it('dispatches event on webhook deletion', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);

        Volt::test('api.webhooks')
            ->call('confirmDelete', $webhook->id)
            ->call('deleteWebhook')
            ->assertDispatched('webhook-deleted');
    });

    it('can re-enable a disabled webhook', function () {
        $webhook = Webhook::factory()->disabled()->create([
            'user_id' => $this->user->id,
            'consecutive_failures' => 18,
        ]);

        Volt::test('api.webhooks')
            ->call('reenableWebhook', $webhook->id)
            ->assertHasNoErrors();

        expect($webhook->fresh()->is_active)->toBeTrue();
        expect($webhook->fresh()->consecutive_failures)->toBe(0);
        expect($webhook->fresh()->disabled_at)->toBeNull();
    });

    it('dispatches event on webhook re-enable', function () {
        $webhook = Webhook::factory()->disabled()->create([
            'user_id' => $this->user->id,
            'consecutive_failures' => 18,
        ]);

        Volt::test('api.webhooks')
            ->call('reenableWebhook', $webhook->id)
            ->assertDispatched('webhook-reenabled');
    });

    it('can view delivery logs', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);
        WebhookLog::factory()->count(5)->create(['webhook_id' => $webhook->id]);

        Volt::test('api.webhooks')
            ->call('viewLogs', $webhook->id)
            ->assertSet('showLogsModal', true)
            ->assertSet('viewingLogsWebhookId', $webhook->id);
    });

    it('can close the logs modal', function () {
        $webhook = Webhook::factory()->create(['user_id' => $this->user->id]);

        Volt::test('api.webhooks')
            ->call('viewLogs', $webhook->id)
            ->assertSet('showLogsModal', true)
            ->call('closeLogsModal')
            ->assertSet('showLogsModal', false)
            ->assertSet('viewingLogsWebhookId', null);
    });

    it('can dismiss secret display', function () {
        $component = Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook');

        $component->assertSet('showOutboundSecret', true);

        $component->call('dismissOutboundSecret')
            ->assertSet('showOutboundSecret', false)
            ->assertSet('plainOutboundSecret', '');
    });

    it('assigns webhook to current user on creation', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['user.created'])
            ->call('createWebhook');

        expect(Webhook::first()->user_id)->toBe($this->user->id);
    });

    it('groups available events by resource prefix', function () {
        Volt::test('api.webhooks')->tap(function ($component) {
            /** @var array<string, array{label: string, events: list<string>}> $groups */
            $groups = $component->instance()->eventGroups();

            expect($groups)->toHaveKey('member');
            expect($groups['member']['label'])->toBe('Member');
            expect($groups['member']['events'])->toContain('member.created', 'member.updated', 'member.archived');
            expect($groups['member']['events'])->not->toContain('user.created');
        });
    });

    it('headline-cases multi-word event group labels', function () {
        Volt::test('api.webhooks')->tap(function ($component) {
            /** @var array<string, array{label: string, events: list<string>}> $groups */
            $groups = $component->instance()->eventGroups();

            expect($groups)->toHaveKey('stock_level');
            expect($groups['stock_level']['label'])->toBe('Stock Level');
            expect($groups)->toHaveKey('rate_definition');
            expect($groups['rate_definition']['label'])->toBe('Rate Definition');
        });
    });

    it('groups a webhook\'s subscribed events for display', function () {
        Volt::test('api.webhooks')->tap(function ($component) {
            /** @var array<string, list<string>> $grouped */
            $grouped = $component->instance()->groupedEvents([
                'member.created',
                'member.updated',
                'user.deleted',
            ]);

            expect($grouped)->toBe([
                'Member' => ['created', 'updated'],
                'User' => ['deleted'],
            ]);
        });
    });

    it('groups the wildcard event under All', function () {
        Volt::test('api.webhooks')->tap(function ($component) {
            /** @var array<string, list<string>> $grouped */
            $grouped = $component->instance()->groupedEvents(['*']);

            expect($grouped)->toBe(['All' => ['all']]);
        });
    });

    it('renders the grouped event count in the table', function () {
        Webhook::factory()->create([
            'user_id' => $this->user->id,
            'events' => ['member.created', 'member.updated', 'user.deleted'],
        ]);

        Volt::test('api.webhooks')
            ->assertSee('3 events')
            ->assertSee('Member:')
            ->assertSee('User:');
    });

    it('renders singular event label for a single event', function () {
        Webhook::factory()->create([
            'user_id' => $this->user->id,
            'events' => ['member.created'],
        ]);

        Volt::test('api.webhooks')
            ->assertSee('1 event');
    });

    it('can create a webhook selecting events through the grouped picker model', function () {
        Volt::test('api.webhooks')
            ->set('createUrl', 'https://example.com/webhook')
            ->set('createEvents', ['member.created', 'member.archived'])
            ->call('createWebhook')
            ->assertHasNoErrors();

        expect(Webhook::first()->events)->toBe(['member.created', 'member.archived']);
    });

    it('can edit a webhook selecting events through the grouped picker model', function () {
        $webhook = Webhook::factory()->create([
            'user_id' => $this->user->id,
            'events' => ['user.created'],
        ]);

        Volt::test('api.webhooks')
            ->call('openEditModal', $webhook->id)
            ->set('editEvents', ['stock_level.created', 'stock_level.updated'])
            ->call('updateWebhook')
            ->assertHasNoErrors();

        expect($webhook->fresh()->events)->toBe(['stock_level.created', 'stock_level.updated']);
    });
});

describe('inbound webhook sources', function () {
    it('shows empty state when no inbound sources exist', function () {
        Volt::test('api.webhooks')
            ->call('setTab', 'inbound')
            ->assertSee('No inbound sources');
    });

    it('can create an inbound source with secret shown once', function () {
        Volt::test('api.webhooks')
            ->set('createInboundName', 'Stripe Payments')
            ->set('createInboundSlug', 'stripe-payments')
            ->set('createInboundPriceWeight', 2)
            ->call('createInboundSource')
            ->assertHasNoErrors()
            ->assertSet('showInboundSecret', true)
            ->assertNotSet('plainInboundSecret', '');

        $source = InboundWebhookSource::first();
        expect($source)->not->toBeNull();
        expect($source->name)->toBe('Stripe Payments');
        expect($source->slug)->toBe('stripe-payments');
        expect($source->price_weight)->toBe(2);
    });

    it('auto-slugs the inbound source name on create', function () {
        Volt::test('api.webhooks')
            ->set('createInboundName', 'My External Service')
            ->assertSet('createInboundSlug', 'my-external-service');
    });

    it('validates inbound slug uniqueness', function () {
        InboundWebhookSource::factory()->create(['slug' => 'existing-source']);

        Volt::test('api.webhooks')
            ->set('createInboundName', 'Existing Source')
            ->set('createInboundSlug', 'existing-source')
            ->call('createInboundSource')
            ->assertHasErrors(['createInboundSlug']);
    });

    it('can edit an inbound source', function () {
        $source = InboundWebhookSource::factory()->create([
            'name' => 'Original',
            'slug' => 'original',
            'price_weight' => 1,
        ]);

        Volt::test('api.webhooks')
            ->call('openInboundEditModal', $source->id)
            ->set('editInboundName', 'Updated Name')
            ->set('editInboundPriceWeight', 3)
            ->call('updateInboundSource')
            ->assertHasNoErrors();

        expect($source->fresh()->name)->toBe('Updated Name');
        expect($source->fresh()->price_weight)->toBe(3);
    });

    it('rotates the inbound secret and shows a new secret', function () {
        $source = InboundWebhookSource::factory()->create();
        $originalSecret = $source->secret;

        $component = Volt::test('api.webhooks')
            ->call('rotateInboundSecret', $source->id)
            ->assertSet('showInboundSecret', true);

        expect($component->get('plainInboundSecret'))->not->toBe('');
        expect($source->fresh()->secret)->not->toBe($originalSecret);
    });

    it('can toggle inbound source active state', function () {
        $source = InboundWebhookSource::factory()->create(['is_active' => true]);

        Volt::test('api.webhooks')
            ->call('toggleInboundActive', $source->id)
            ->assertHasNoErrors();

        expect($source->fresh()->is_active)->toBeFalse();

        Volt::test('api.webhooks')
            ->call('toggleInboundActive', $source->id);

        expect($source->fresh()->is_active)->toBeTrue();
    });

    it('can delete an inbound source', function () {
        $source = InboundWebhookSource::factory()->create();

        Volt::test('api.webhooks')
            ->call('confirmInboundDelete', $source->id)
            ->call('deleteInboundSource')
            ->assertHasNoErrors();

        expect(InboundWebhookSource::count())->toBe(0);
    });

    it('shows the receive URL for an inbound source', function () {
        InboundWebhookSource::factory()->create(['slug' => 'stripe-payments']);

        Volt::test('api.webhooks')
            ->call('setTab', 'inbound')
            ->assertSee('/webhooks/receive/stripe-payments');
    });

    it('can view inbound delivery logs', function () {
        $source = InboundWebhookSource::factory()->create();
        InboundWebhookLog::factory()->count(3)->create([
            'inbound_webhook_source_id' => $source->id,
        ]);

        Volt::test('api.webhooks')
            ->call('viewInboundLogs', $source->id)
            ->assertSet('showInboundLogsModal', true)
            ->assertSet('viewingInboundLogsSourceId', $source->id);
    });

    it('can dismiss inbound secret display', function () {
        $component = Volt::test('api.webhooks')
            ->set('createInboundName', 'Test Source')
            ->set('createInboundSlug', 'test-source')
            ->call('createInboundSource');

        $component->assertSet('showInboundSecret', true);

        $component->call('dismissInboundSecret')
            ->assertSet('showInboundSecret', false)
            ->assertSet('plainInboundSecret', '');
    });
});

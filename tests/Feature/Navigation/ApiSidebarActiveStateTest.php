<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'api.access',
        'api.explorer.use',
        'api.logs.view',
        'api.usage.view',
        'webhooks.manage',
    ]);
    $this->actingAs($this->user);
});

/**
 * Asserts that, on the given page, the anchor pointing at $hrefRouteName is
 * rendered with the `active` class inside the API sidebar nav.
 */
function assertApiNavLinkActive(string $html, string $hrefRouteName): void
{
    $href = route($hrefRouteName);

    preg_match_all('/<nav class="s-admin-sidebar">.*?<\/nav>/s', $html, $navMatches);

    expect($navMatches[0])->not->toBeEmpty('Expected an API sidebar nav block');

    $sidebar = $navMatches[0][0];

    preg_match_all('/<a\b[^>]*>/', $sidebar, $matches);

    $matching = collect($matches[0])
        ->filter(fn (string $tag) => str_contains($tag, 'href="'.$href.'"'));

    expect($matching)->not->toBeEmpty("No anchor found for {$hrefRouteName}");

    expect($matching->contains(fn (string $tag) => str_contains($tag, 'active')))
        ->toBeTrue("Expected an active anchor for {$hrefRouteName}");
}

describe('api sidebar presence', function () {
    it('renders all five sidebar items on the api index page', function () {
        $html = $this->get(route('api.index'))->assertOk()->getContent();

        expect($html)->toContain(route('api.index'));
        expect($html)->toContain(route('api.explorer'));
        expect($html)->toContain(route('api.logs'));
        expect($html)->toContain(route('api.usage'));
        expect($html)->toContain(route('api.webhooks'));
        expect(substr_count($html, 's-admin-nav-item'))->toBeGreaterThanOrEqual(5);
    });

    it('renders the sidebar on each api console page', function () {
        foreach (['api.index', 'api.explorer', 'api.logs', 'api.usage', 'api.webhooks'] as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();

            expect($html)->toContain('s-admin-sidebar');
            expect($html)->toContain('Overview');
            expect($html)->toContain('Explorer');
        }
    });
});

describe('api sidebar active state', function () {
    it('marks overview active on the api index page', function () {
        $html = $this->get(route('api.index'))->assertOk()->getContent();

        assertApiNavLinkActive($html, 'api.index');
    });

    it('marks explorer active on the explorer page', function () {
        $html = $this->get(route('api.explorer'))->assertOk()->getContent();

        assertApiNavLinkActive($html, 'api.explorer');
    });

    it('marks request log active on the logs page', function () {
        $html = $this->get(route('api.logs'))->assertOk()->getContent();

        assertApiNavLinkActive($html, 'api.logs');
    });

    it('marks usage active on the usage page', function () {
        $html = $this->get(route('api.usage'))->assertOk()->getContent();

        assertApiNavLinkActive($html, 'api.usage');
    });

    it('marks webhooks active on the webhooks page', function () {
        $html = $this->get(route('api.webhooks'))->assertOk()->getContent();

        assertApiNavLinkActive($html, 'api.webhooks');
    });
});

describe('api sidebar permission gating', function () {
    it('hides gated sidebar items when the user lacks sub-permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access');

        $html = $this->actingAs($user)
            ->get(route('api.index'))
            ->assertOk()
            ->getContent();

        expect($html)->toContain(route('api.index'));
        expect($html)->not->toContain(route('api.explorer'));
        expect($html)->not->toContain(route('api.logs'));
    });
});

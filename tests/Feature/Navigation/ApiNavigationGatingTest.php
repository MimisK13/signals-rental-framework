<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

describe('header api link gating on api.access', function () {
    it('shows the extensions api link to a user with api.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('api.index'), false);
    });

    it('hides the extensions api link from a user without api.access', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('api.index'), false);
    });

    it('marks the extensions api link active when viewing an api console page', function () {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'api.access',
            'api.explorer.use',
        ]);

        $html = $this->actingAs($user)
            ->get(route('api.explorer'))
            ->assertOk()
            ->getContent();

        $href = route('api.index');
        preg_match_all('/<a\b[^>]*>/', $html, $matches);

        $apiLink = collect($matches[0])
            ->first(fn (string $tag) => str_contains($tag, 'href="'.$href.'"') && str_contains($tag, 'mega-item'));

        expect($apiLink)->not->toBeNull();
        expect(str_contains($apiLink, 'active'))->toBeTrue();
    });

    it('shows the mobile sidebar api link to a user with api.access', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access');

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        expect(substr_count($html, route('api.index')))->toBeGreaterThanOrEqual(2);
    });

    it('hides the mobile sidebar api link from a user without api.access', function () {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        expect($html)->not->toContain(route('api.index'));
    });
});

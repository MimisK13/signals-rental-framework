<?php

use App\Domain\Administration\Models\User;
use App\Domain\Lists\Database\Seeders\ListOfValuesSeeder;
use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Livewire\LinkForm;
use App\Domain\Members\Presentation\Livewire\Links;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the links page', function () {
    $this->get("/members/{$this->member->id}/links")
        ->assertOk()
        ->assertSee('Links');
});

it('lists links for a member', function () {
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://example.com',
        'name' => 'Example Site',
    ]);
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://other.com',
        'name' => 'Other Site',
    ]);

    Livewire::test(Links::class, ['member' => $this->member])
        ->assertSee('https://example.com')
        ->assertSee('https://other.com');
});

it('shows empty state when no links exist', function () {
    Livewire::test(Links::class, ['member' => $this->member])
        ->assertSee('No links found.');
});

it('can delete a link', function () {
    $link = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
    ]);

    Livewire::test(Links::class, ['member' => $this->member])
        ->call('deleteLink', $link->id);

    expect(Link::find($link->id))->toBeNull();
});

it('renders the create link form', function () {
    $this->get("/members/{$this->member->id}/links/create")
        ->assertOk()
        ->assertSee('Add Link');
});

it('populates the type dropdown with the seeded Link Type list values', function () {
    // Regression: the form queried 'LinkType' (no space) but the seeded list
    // name is 'Link Type', so the Type select rendered blank.
    $this->seed(ListOfValuesSeeder::class);

    Livewire::test(LinkForm::class, ['member' => $this->member])
        ->assertViewHas('linkTypes', fn ($types) => $types->isNotEmpty())
        ->assertSee('Website')
        ->assertSee('LinkedIn')
        ->assertSee('YouTube');
});

it('can create a link', function () {
    Livewire::test(LinkForm::class, ['member' => $this->member])
        ->set('url', 'https://newsite.com')
        ->set('name', 'New Site')
        ->call('save')
        ->assertRedirect();

    expect($this->member->links()->where('url', 'https://newsite.com')->exists())->toBeTrue();
});

it('can edit a link', function () {
    $link = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $this->member->id,
        'url' => 'https://old.com',
        'name' => 'Old Site',
    ]);

    Livewire::test(LinkForm::class, ['member' => $this->member, 'link' => $link])
        ->assertSet('url', 'https://old.com')
        ->assertSet('name', 'Old Site')
        ->set('url', 'https://new.com')
        ->set('name', 'New Site')
        ->call('save')
        ->assertRedirect();

    expect($link->fresh()->url)->toBe('https://new.com');
    expect($link->fresh()->name)->toBe('New Site');
});

it('validates required url', function () {
    Livewire::test(LinkForm::class, ['member' => $this->member])
        ->set('url', '')
        ->call('save')
        ->assertHasErrors(['url']);
});

it('validates url format', function () {
    Livewire::test(LinkForm::class, ['member' => $this->member])
        ->set('url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['url']);
});

it('cannot delete another member\'s link', function () {
    $otherMember = Member::factory()->create();
    $otherLink = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $otherMember->id,
    ]);

    Livewire::test(Links::class, ['member' => $this->member])
        ->call('deleteLink', $otherLink->id)
        ->assertNotFound();

    expect(Link::find($otherLink->id))->not->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/links")
        ->assertRedirect();
});

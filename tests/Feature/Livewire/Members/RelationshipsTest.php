<?php

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberRelationship;
use App\Domain\Members\Presentation\Livewire\Relationships;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the relationships page for a contact', function () {
    $contact = Member::factory()->contact()->create();

    $this->get("/members/{$contact->id}/relationships")
        ->assertOk()
        ->assertSee('Relationships');
});

it('renders the relationships page for an organisation', function () {
    $org = Member::factory()->organisation()->create();

    $this->get("/members/{$org->id}/relationships")
        ->assertOk()
        ->assertSee('Relationships');
});

it('lists related organisations for a contact', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create(['name' => 'Acme Corp']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Employee',
    ]);

    Livewire::test(Relationships::class, ['member' => $contact])
        ->assertSee('Acme Corp')
        ->assertSee('Employee');
});

it('lists related contacts for an organisation', function () {
    $org = Member::factory()->organisation()->create();
    $contact = Member::factory()->contact()->create(['name' => 'John Smith']);

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Director',
    ]);

    Livewire::test(Relationships::class, ['member' => $org])
        ->assertSee('John Smith')
        ->assertSee('Director');
});

it('displays a relationship on both members regardless of direction', function () {
    $org = Member::factory()->organisation()->create(['name' => 'Acme Corp']);
    $contact = Member::factory()->contact()->create(['name' => 'Jane Doe']);

    // Stored with the organisation as member_id (the "wrong" direction for a
    // contact-centric query) to prove the listing unions both columns.
    MemberRelationship::factory()->create([
        'member_id' => $org->id,
        'related_member_id' => $contact->id,
        'relationship_type' => 'Director',
    ]);

    Livewire::test(Relationships::class, ['member' => $contact])
        ->assertSee('Acme Corp');

    Livewire::test(Relationships::class, ['member' => $org])
        ->assertSee('Jane Doe');
});

it('shows empty state when no relationships exist', function () {
    $member = Member::factory()->contact()->create();

    Livewire::test(Relationships::class, ['member' => $member])
        ->assertSee('No relationships found.');
});

it('shows primary badge on primary relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    MemberRelationship::factory()->primary()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Livewire::test(Relationships::class, ['member' => $contact])
        ->assertSee('Primary');
});

it('can delete a relationship', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    $relationship = MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    Livewire::test(Relationships::class, ['member' => $contact])
        ->call('deleteRelationship', $relationship->id);

    expect(MemberRelationship::find($relationship->id))->toBeNull();
});

it('requires authentication', function () {
    $member = Member::factory()->create();
    auth()->logout();

    $this->get("/members/{$member->id}/relationships")
        ->assertRedirect();
});

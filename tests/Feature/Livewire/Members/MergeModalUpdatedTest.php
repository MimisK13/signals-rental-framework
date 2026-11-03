<?php

use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Livewire\MergeModal;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

it('casts a selected secondary member id to an integer when updated', function () {
    $member = Member::factory()->contact()->create();

    // Setting memberBId fires updatedMemberBId, which coerces the string value to
    // an int (line 51).
    Livewire::test(MergeModal::class)
        ->set('memberBId', (string) $member->id)
        ->assertSet('memberBId', $member->id);
});

it('nulls the secondary member id when cleared to an empty string', function () {
    Livewire::test(MergeModal::class)
        ->set('memberBId', '')
        ->assertSet('memberBId', null);
});

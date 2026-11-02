<?php

declare(strict_types=1);

use App\Domain\Attachments\Models\Attachment;
use App\Domain\Attachments\Policies\AttachmentPolicy;
use App\Domain\Attachments\Presentation\Livewire\MemberFiles;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('uses the canonical attachment model and preserves parent morph values', function (): void {
    $member = Member::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => $member->getMorphClass(),
        'attachable_id' => $member->id,
    ]);

    expect($member->attachments()->whereKey($attachment)->first())->toBeInstanceOf(Attachment::class)
        ->and($attachment->attachable)->toBeInstanceOf(Member::class)
        ->and(Gate::getPolicyFor(Attachment::class))->toBeInstanceOf(AttachmentPolicy::class)
        ->and(class_exists('App\Models\Attachment'))->toBeFalse();
});

it('serves the native member files route', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    $member = Member::factory()->create();

    $this->actingAs($user)->withoutVite()
        ->get(route('members.files', $member))
        ->assertOk()
        ->assertSeeLivewire(MemberFiles::class);
});

arch('Attachments presentation does not depend on Volt', function (): void {
    expect('App\Domain\Attachments')->not->toUse('Livewire\Volt');
});

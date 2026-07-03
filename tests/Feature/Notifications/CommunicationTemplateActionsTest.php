<?php

use App\Actions\Notifications\RestoreCommunicationTemplateVersion;
use App\Actions\Notifications\SaveCommunicationTemplate;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\CommunicationTemplateRenderer;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->store = Store::factory()->create();
});

it('creates a communication template', function () {
    $result = (new SaveCommunicationTemplate)(null, SaveCommunicationTemplateData::from([
        'name' => 'Custom Email',
        'slug' => 'custom-email',
        'channel' => 'email',
        'subject' => 'Hi {{ contact.name }}',
        'body' => 'Body content',
        'store_id' => $this->store->id,
        'is_default' => true,
    ]));

    expect($result->name)->toBe('Custom Email');
    expect(CommunicationTemplate::query()->where('slug', 'custom-email')->exists())->toBeTrue();
});

it('updates a communication template and snapshots a version', function () {
    $template = CommunicationTemplate::factory()->create([
        'store_id' => $this->store->id,
        'body' => 'Original body',
        'version' => 2,
    ]);

    (new SaveCommunicationTemplate)($template, SaveCommunicationTemplateData::from([
        'name' => $template->name,
        'slug' => $template->slug,
        'channel' => $template->channel,
        'subject' => $template->subject,
        'body' => 'Updated body',
        'store_id' => $this->store->id,
        'change_note' => 'Edited intro',
    ]));

    $template->refresh();

    expect($template->body)->toBe('Updated body');
    expect($template->version)->toBe(3);

    $version = CommunicationTemplateVersion::query()
        ->where('communication_template_id', $template->id)
        ->where('version', 2)
        ->first();

    expect($version)->not->toBeNull();
    expect($version->body)->toBe('Original body');
    expect($version->change_note)->toBe('Edited intro');
});

it('invalidates compiled cache when saving updates', function () {
    Cache::flush();

    $template = CommunicationTemplate::factory()->create([
        'store_id' => $this->store->id,
        'version' => 1,
    ]);

    app(CommunicationTemplateRenderer::class)->compiledSegments($template);
    expect(Cache::has('communication-template:compiled:'.$template->id.':1'))->toBeTrue();

    (new SaveCommunicationTemplate)($template, SaveCommunicationTemplateData::from([
        'name' => $template->name,
        'slug' => $template->slug,
        'channel' => $template->channel,
        'subject' => $template->subject,
        'body' => 'Changed',
        'store_id' => $this->store->id,
    ]));

    expect(Cache::has('communication-template:compiled:'.$template->id.':1'))->toBeFalse();
});

it('restores a previous template version', function () {
    $template = CommunicationTemplate::factory()->create([
        'store_id' => $this->store->id,
        'subject' => 'Current subject',
        'body' => 'Current body',
        'version' => 5,
    ]);

    $version = CommunicationTemplateVersion::factory()->create([
        'communication_template_id' => $template->id,
        'version' => 3,
        'subject' => 'Old subject',
        'body' => 'Old body',
    ]);

    (new RestoreCommunicationTemplateVersion)($template, $version);

    $template->refresh();

    expect($template->subject)->toBe('Old subject');
    expect($template->body)->toBe('Old body');
    expect($template->version)->toBe(6);

    expect(CommunicationTemplateVersion::query()
        ->where('communication_template_id', $template->id)
        ->where('version', 5)
        ->exists())->toBeTrue();
});

it('forbids updating system templates', function () {
    $template = CommunicationTemplate::factory()->system()->create([
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new SaveCommunicationTemplate)($template, SaveCommunicationTemplateData::from([
        'name' => $template->name,
        'slug' => $template->slug,
        'channel' => $template->channel,
        'subject' => $template->subject,
        'body' => 'Changed',
        'store_id' => $this->store->id,
    ])))->toThrow(ValidationException::class);
});

it('forbids duplicate slugs within the same store', function () {
    CommunicationTemplate::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'duplicate-slug',
    ]);

    expect(fn () => (new SaveCommunicationTemplate)(null, SaveCommunicationTemplateData::from([
        'name' => 'Another',
        'slug' => 'duplicate-slug',
        'channel' => 'email',
        'subject' => 'Hi',
        'body' => 'Body',
        'store_id' => $this->store->id,
    ])))->toThrow(ValidationException::class);
});

it('forbids unauthorized users from creating templates', function () {
    $viewer = User::factory()->create(['is_owner' => false]);
    $this->actingAs($viewer);

    expect(fn () => (new SaveCommunicationTemplate)(null, SaveCommunicationTemplateData::from([
        'name' => 'Blocked',
        'slug' => 'blocked',
        'channel' => 'email',
        'subject' => 'Hi',
        'body' => 'Body',
        'store_id' => $this->store->id,
    ])))->toThrow(AuthorizationException::class);
});

it('returns 404 when restoring a version from another template', function () {
    $template = CommunicationTemplate::factory()->create(['store_id' => $this->store->id]);
    $foreignVersion = CommunicationTemplateVersion::factory()->create();

    expect(fn () => (new RestoreCommunicationTemplateVersion)($template, $foreignVersion))
        ->toThrow(HttpException::class);
});

describe('policy matrix', function () {
    beforeEach(function () {
        $member = Member::factory()->create();
        $this->manager = User::factory()->create([
            'member_id' => $member->id,
            'is_owner' => false,
        ]);
        $this->manager->givePermissionTo('notifications.manage');
        MemberStore::create([
            'member_id' => $member->id,
            'store_id' => $this->store->id,
            'created_at' => now(),
        ]);
    });

    it('allows permitted users to manage templates in their store', function () {
        $this->actingAs($this->manager);

        $template = CommunicationTemplate::factory()->create(['store_id' => $this->store->id]);

        expect($this->manager->can('update', $template))->toBeTrue();
        expect($this->manager->can('delete', $template))->toBeTrue();
    });

    it('denies deleting system templates', function () {
        $this->actingAs($this->manager);

        $template = CommunicationTemplate::factory()->system()->create(['store_id' => $this->store->id]);

        expect($this->manager->can('delete', $template))->toBeFalse();
    });
});

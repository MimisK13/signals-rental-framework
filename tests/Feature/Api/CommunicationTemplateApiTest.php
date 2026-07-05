<?php

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->owner->givePermissionTo('notifications.manage');
    $this->store = Store::factory()->create();
    $this->notificationType = NotificationType::factory()->create();
});

/**
 * @param  list<string>  $abilities
 */
function communicationTemplateApiToken(User $user, array $abilities = ['communication-templates:read', 'communication-templates:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('GET /api/v1/communication_templates', function () {
    it('lists communication templates', function () {
        CommunicationTemplate::factory()->count(2)->create(['store_id' => $this->store->id]);
        $token = communicationTemplateApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/communication_templates')
            ->assertOk()
            ->assertJsonStructure([
                'communication_templates' => [
                    '*' => ['id', 'name', 'slug', 'channel', 'version', 'is_active'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('requires communication-templates:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/communication_templates')
            ->assertForbidden();
    });
});

describe('communication template CRUD and versions', function () {
    it('creates, shows, updates with version snapshots, lists versions, and deletes custom templates', function () {
        $token = communicationTemplateApiToken($this->owner);

        $createResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/communication_templates', [
                'store_id' => $this->store->id,
                'notification_type_id' => $this->notificationType->id,
                'name' => 'Custom Email',
                'slug' => 'custom-email',
                'channel' => 'email',
                'subject' => 'Hello there',
                'body' => 'Version 1 body',
            ])
            ->assertCreated();

        $templateId = $createResponse->json('communication_template.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/communication_templates/{$templateId}")
            ->assertOk()
            ->assertJsonPath('communication_template.name', 'Custom Email');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/communication_templates/{$templateId}", [
                'name' => 'Custom Email',
                'slug' => 'custom-email',
                'channel' => 'email',
                'subject' => 'Hello there',
                'body' => 'Version 2 body',
                'change_note' => 'Updated body',
            ])
            ->assertOk()
            ->assertJsonPath('communication_template.version', 2)
            ->assertJsonPath('communication_template.body', 'Version 2 body');

        expect(CommunicationTemplateVersion::query()->where('communication_template_id', $templateId)->count())->toBe(1);

        $versionsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/communication_templates/{$templateId}/versions")
            ->assertOk()
            ->assertJsonStructure([
                'communication_template_versions' => [
                    '*' => ['id', 'version', 'subject', 'body', 'change_note'],
                ],
            ]);

        expect($versionsResponse->json('communication_template_versions.0.body'))->toBe('Version 1 body');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/communication_templates/{$templateId}")
            ->assertNoContent();

        expect(CommunicationTemplate::withTrashed()->find($templateId)?->trashed())->toBeTrue();
    });

    it('restores content from a historical version snapshot', function () {
        $token = communicationTemplateApiToken($this->owner);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/communication_templates', [
                'store_id' => $this->store->id,
                'notification_type_id' => $this->notificationType->id,
                'name' => 'Versioned',
                'slug' => 'versioned-template',
                'channel' => 'email',
                'subject' => 'Original subject',
                'body' => 'Original body',
            ])
            ->assertCreated();

        $templateId = $create->json('communication_template.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/communication_templates/{$templateId}", [
                'name' => 'Versioned',
                'slug' => 'versioned-template',
                'channel' => 'email',
                'subject' => 'Changed subject',
                'body' => 'Changed body',
            ])
            ->assertOk();

        $version = CommunicationTemplateVersion::query()
            ->where('communication_template_id', $templateId)
            ->where('version', 1)
            ->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/communication_templates/{$templateId}/versions/{$version->id}/restore")
            ->assertOk()
            ->assertJsonPath('communication_template.body', 'Original body')
            ->assertJsonPath('communication_template.subject', 'Original subject')
            ->assertJsonPath('communication_template.version', 3);
    });

    it('requires communication-templates:write ability for mutations', function () {
        $template = CommunicationTemplate::factory()->create(['store_id' => $this->store->id]);
        $readOnlyToken = $this->owner->createToken('test', ['communication-templates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
            ->postJson('/api/v1/communication_templates', [
                'store_id' => $this->store->id,
                'name' => 'Blocked',
                'slug' => 'blocked',
                'channel' => 'email',
                'body' => 'Body',
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
            ->putJson("/api/v1/communication_templates/{$template->id}", [
                'name' => $template->name,
                'slug' => $template->slug,
                'channel' => $template->channel,
                'body' => 'Changed',
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
            ->deleteJson("/api/v1/communication_templates/{$template->id}")
            ->assertForbidden();

        $version = CommunicationTemplateVersion::factory()->create([
            'communication_template_id' => $template->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
            ->postJson("/api/v1/communication_templates/{$template->id}/versions/{$version->id}/restore")
            ->assertForbidden();
    });
});

describe('store scoping on update', function () {
    it('blocks moving a template to a store the user cannot access', function () {
        $storeB = Store::factory()->create();

        $restricted = User::factory()->create(['is_owner' => false, 'is_admin' => false]);
        MemberStore::create([
            'member_id' => $restricted->member_id,
            'store_id' => $this->store->id,
            'created_at' => now(),
        ]);
        $restricted->givePermissionTo('notifications.manage');

        $template = CommunicationTemplate::factory()->create(['store_id' => $this->store->id]);
        $token = communicationTemplateApiToken($restricted);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/communication_templates/{$template->id}", [
                'store_id' => $storeB->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'channel' => $template->channel,
                'body' => 'Attempted move',
            ])
            ->assertNotFound();

        expect($template->fresh()->store_id)->toBe($this->store->id);
    });
});

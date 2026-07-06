<?php

use App\Models\CommunicationTemplate;
use App\Models\NotificationType;
use App\Models\Store;
use App\Services\Notifications\SystemCommunicationTemplates;
use Database\Seeders\NotificationTypeSeeder;

beforeEach(function () {
    $this->seed(NotificationTypeSeeder::class);
    $this->store = Store::factory()->create();
});

function refreshSystemCommunicationTemplatesMigration(): object
{
    return require database_path('migrations/2026_07_03_201500_refresh_system_communication_templates.php');
}

it('seeds system communication templates for existing stores via migration', function () {
    expect(CommunicationTemplate::query()->where('store_id', $this->store->id)->count())->toBe(0);

    refreshSystemCommunicationTemplatesMigration()->up();

    expect(CommunicationTemplate::query()->where('store_id', $this->store->id)->count())
        ->toBe(count(SystemCommunicationTemplates::seededTypeKeys()));

    $confirmed = CommunicationTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('slug', 'opportunity-confirmed-email')
        ->first();

    expect($confirmed)->not->toBeNull()
        ->and($confirmed->is_system)->toBeTrue()
        ->and($confirmed->body)->toBe(SystemCommunicationTemplates::definition('opportunity.confirmed')['body']);
});

it('does not overwrite communication templates that already exist for a store', function () {
    $definition = SystemCommunicationTemplates::definition('opportunity.confirmed');
    $typeId = NotificationType::query()->where('key', 'opportunity.confirmed')->value('id');

    $existing = CommunicationTemplate::query()->create([
        'notification_type_id' => $typeId,
        'store_id' => $this->store->id,
        'name' => 'Custom Confirmation',
        'slug' => $definition['slug'],
        'channel' => 'email',
        'subject' => 'Custom subject',
        'body' => 'Custom body that must not change',
        'attachments_config' => null,
        'is_default' => false,
        'is_system' => false,
        'is_active' => true,
        'version' => 1,
        'last_edited_at' => now(),
    ]);

    refreshSystemCommunicationTemplatesMigration()->up();

    $existing->refresh();

    expect($existing->body)->toBe('Custom body that must not change')
        ->and($existing->subject)->toBe('Custom subject')
        ->and($existing->is_system)->toBeFalse();
});

it('has a no-op down method', function () {
    refreshSystemCommunicationTemplatesMigration()->up();

    $countBefore = CommunicationTemplate::query()->where('store_id', $this->store->id)->count();

    refreshSystemCommunicationTemplatesMigration()->down();

    expect(CommunicationTemplate::query()->where('store_id', $this->store->id)->count())
        ->toBe($countBefore);
});

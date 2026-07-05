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

function refreshShortageEscalationNotificationTypesMigration(): object
{
    return require database_path('migrations/2026_07_04_031748_refresh_shortage_escalation_and_virtual_stock_overrun_notification_types.php');
}

it('upserts virtual_stock.overrun for existing installs without overwriting custom templates', function () {
    expect(NotificationType::query()->where('key', 'virtual_stock.overrun')->exists())->toBeTrue();

    $definition = SystemCommunicationTemplates::definition('virtual_stock.overrun');
    $typeId = NotificationType::query()->where('key', 'virtual_stock.overrun')->value('id');

    CommunicationTemplate::query()->create([
        'notification_type_id' => $typeId,
        'store_id' => $this->store->id,
        'name' => 'Custom Overrun',
        'slug' => $definition['slug'],
        'channel' => 'email',
        'subject' => 'Custom subject',
        'body' => 'Custom body must remain',
        'attachments_config' => null,
        'is_default' => false,
        'is_system' => false,
        'is_active' => true,
        'version' => 1,
        'last_edited_at' => now(),
    ]);

    refreshShortageEscalationNotificationTypesMigration()->up();

    $template = CommunicationTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('slug', $definition['slug'])
        ->first();

    expect($template)->not->toBeNull()
        ->and($template->body)->toBe('Custom body must remain');
});

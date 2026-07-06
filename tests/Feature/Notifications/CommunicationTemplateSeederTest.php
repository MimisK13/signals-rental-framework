<?php

use App\Actions\Stores\CreateStore;
use App\Data\Stores\CreateStoreData;
use App\Models\CommunicationTemplate;
use App\Models\NotificationType;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\SystemCommunicationTemplates;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('seeds system communication templates for an existing store', function () {
    $store = Store::factory()->create();

    app(CommunicationTemplateSeeder::class)->seedForStore($store);

    expect(CommunicationTemplate::query()->where('store_id', $store->id)->count())
        ->toBe(count(SystemCommunicationTemplates::seededTypeKeys()));

    $confirmed = CommunicationTemplate::query()
        ->where('store_id', $store->id)
        ->where('slug', 'opportunity-confirmed-email')
        ->first();

    expect($confirmed)->not->toBeNull();
    expect($confirmed->is_system)->toBeTrue();
    expect($confirmed->is_default)->toBeTrue();
    expect($confirmed->channel)->toBe('email');
    expect($confirmed->subject)->toContain('{{ opportunity.reference');
});

it('is idempotent when re-run for the same store', function () {
    $store = Store::factory()->create();
    $seeder = app(CommunicationTemplateSeeder::class);

    $seeder->seedForStore($store);
    $count = CommunicationTemplate::query()->where('store_id', $store->id)->count();

    $seeder->seedForStore($store);

    expect(CommunicationTemplate::query()->where('store_id', $store->id)->count())->toBe($count);
});

it('seeds communication templates when a new store is created', function () {
    $store = (new CreateStore)(CreateStoreData::from([
        'name' => 'North Warehouse',
        'country_code' => 'GB',
    ]));

    expect(CommunicationTemplate::query()->where('store_id', $store->id)->count())
        ->toBeGreaterThan(0);
});

it('links seeded templates to notification types', function () {
    $store = Store::factory()->create();

    app(CommunicationTemplateSeeder::class)->seedForStore($store);

    $typeId = NotificationType::query()->where('key', 'shortage.waitlist.matched')->value('id');

    expect(CommunicationTemplate::query()
        ->where('store_id', $store->id)
        ->where('notification_type_id', $typeId)
        ->where('slug', 'shortage-waitlist-matched-email')
        ->exists())->toBeTrue();
});

it('seeds required P25 wired notification templates', function () {
    $store = Store::factory()->create();

    app(CommunicationTemplateSeeder::class)->seedForStore($store);

    foreach ([
        'opportunity-created-email',
        'opportunity-confirmed-email',
        'opportunity-cancelled-email',
        'shortage-waitlist-matched-email',
    ] as $slug) {
        expect(CommunicationTemplate::query()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->exists())->toBeTrue("Missing seeded template: {$slug}");
    }
});

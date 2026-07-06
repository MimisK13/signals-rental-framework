<?php

use App\Models\CommunicationTemplate;
use App\Models\DocumentTemplate;
use App\Models\Store;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;

it('seeds document and communication templates for every store via DatabaseSeeder', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(StoreSeeder::class);
    $this->seed(NotificationTypeSeeder::class);

    expect(DocumentTemplate::query()->count())->toBe(0)
        ->and(CommunicationTemplate::query()->count())->toBe(0);

    $this->seed(DocumentTemplateSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);

    $storeCount = Store::query()->count();

    expect(DocumentTemplate::query()->count())->toBeGreaterThan(0)
        ->and(CommunicationTemplate::query()->count())->toBeGreaterThan(0)
        ->and(DocumentTemplate::query()->distinct('store_id')->count('store_id'))->toBe($storeCount);
});

it('does not duplicate templates when seeders re-run', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(StoreSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->seed(DocumentTemplateSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);

    $documentCount = DocumentTemplate::query()->count();
    $communicationCount = CommunicationTemplate::query()->count();

    $this->seed(DocumentTemplateSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);

    expect(DocumentTemplate::query()->count())->toBe($documentCount)
        ->and(CommunicationTemplate::query()->count())->toBe($communicationCount);
});

it('includes template seeders in DatabaseSeeder call list', function () {
    $seeder = new DatabaseSeeder;

    $reflection = new ReflectionClass($seeder);
    $method = $reflection->getMethod('run');
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('DocumentTemplateSeeder::class')
        ->and($source)->toContain('CommunicationTemplateSeeder::class');
});
